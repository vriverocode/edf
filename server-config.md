# Configuración del servidor cPanel — PACIFIK

Este archivo contiene los pasos **manuales** que debes ejecutar en tu servidor cPanel
para corregir las vulnerabilidades que no se resuelven solo con código.

---

## 1. Desactivar modo debug en producción

**Qué hace:** evita que Laravel muestre stack traces completos (rutas del servidor, código fuente, queries SQL) cuando ocurre un error. Cualquier error no controlado devolvía páginas HTML de depuración de cientos de KB.

**Qué vulnerabilidad resuelve:** Hallazgo 1 (crítica) — fuga de información interna del servidor a cualquier usuario, incluso no autenticado.

**Escalabilidad:** solución permanente, aplica a toda la API de una vez. No necesita mantenimiento.

### Pasos:

1.1 Ingresa a cPanel → "File Manager" → navega a la carpeta del proyecto Laravel (ej: `/home4/bluelata/public_html/website_c67adca2/`)

1.2 Abre el archivo `.env` y cambia:

```ini
APP_ENV=production
APP_DEBUG=false
```

1.3 Guarda el archivo.

1.4 En cPanel → "Terminal" (o SSH), ejecuta:

```bash
cd /home4/bluelata/public_html/website_c67adca2
php artisan config:cache
php artisan route:cache
```

### Verificación:
```bash
curl -s -D - "https://website-a40e47dc.gtq.fvz.mybluehost.me/api/user" | head -20
# Debe devolver HTTP 401 con JSON corto, no una página HTML gigante
```

---

## 2. Bloquear acceso público a storage/logs/

### Qué hace: impide que cualquiera descargue el archivo de logs de Laravel (varios MB) que puede contener rutas del servidor, queries SQL, excepciones, y datos sensibles.

### Qué vulnerabilidad resuelve: Hallazgo 2 (APP_DEBUG) — storage/logs/laravel.log descargable sin autenticación.

### Cómo escalable: solución permanente mientras el document root esté bien configurado.

### Paso A — Verificar document root (recomendado)

1. En cPanel → "Domains" → "Document Root"
2. Verifica que apunte a: `/home4/bluelata/public_html/website_c67adca2/public`
3. Si apunta a la raíz (sin `/public`), cámbialo inmediatamente

### Paso B — Si no puedes cambiar el document root, bloquea con .htaccess:

1. En File Manager, navega a la carpeta `storage/` del proyecto
2. Crea un archivo llamado `.htaccess` con este contenido:

```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
```

### Paso C — Rotar el log ya expuesto y credenciales:

1. Desde Terminal/SSH, ejecuta:

```bash
cd /home4/bluelata/public_html/website_c67adca2
mv storage/logs/laravel.log storage/logs/laravel.log.expuesto
php artisan log:clear  # o simplemente truncar el archivo
```

2. Rota las siguientes credenciales que pudieron haber quedado en los logs:
   - Contraseña de la base de datos MySQL
   - Pusher `app_secret`
   - Firebase credentials (si aparecen en logs)
   - Stripe/Culqi API keys
   - Cualquier token de prueba de Sanctum

### Verificación:
```bash
curl -sI "https://website-a40e47dc.gtq.fv2.mybluehost.me/storage/logs/laravel.log"
# Debe devolver HTTP 403 o 404
```

---

## 3. Forzar HTTPS en producción (si no está configurado)

### Qué hace: redirige todo el tráfico HTTP a HTTPS, evitando que tokens y cookies viajen en texto plano.

### Qué vulnerabilidad resuelve: complemento del Hallazgo 6 (cabeceras de seguridad).

### Cómo escalar: solución permanente a nivel de servidor.

### Pasos:

1. En cPanel → "Domains" → "SSL/TLS Status" → verifica que el SSL esté activo
2. En cPanel → "Domains" → "Redirect" → crea un redirect permanente (301):
   - Type: `Permanent (301)`
   - From: `http://web.edificiopacifik.com/` (con y sin www)
   - To: `https://web.edificiopacifik.com/`
   - Redirect all public_html

3. O alternativamente, edita el `.htaccess` en la raíz pública (`public/.htaccess`):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```

---

## 4. Eliminar cabecera `host-header` (Hallazgo 8 — baja prioridad)

### Qué hace: elimina la cabecera que filtra "shared.bluehost.com".

### Es una cabecera propia de Bluehost que no se puede eliminar desde .htaccess. Contacta a soporte de Bluehost para preguntar si pueden desactivarla. Riesgo bajo, prioridad baja.

---

## 5. Activar 2FA en la cuenta admin (URGENTE — inmediato, no requiere deploy)

### Qué hace: protege la cuenta admin (`id=1`, `username: admin`) contra ataques de fuerza bruta y credential stuffing.

### Qué vulnerabilidad mitiga: Hallazgo 3 (IDOR expuso cuenta admin completa + no tiene 2FA)

### Pasos:

1. Inicia sesión en `https://web.edificiopacifik.com/` con la cuenta admin
2. Ve a la configuración de perfil → activa Two Factor Authentication
3. Usa Google Authenticator o similar para escanear el QR
4. Confirma que el 2FA funciona

### Medida adicional (mientras no se corrija el IDOR):
- Cambia el username de la cuenta admin a algo no adivinable (no `admin`)
- Cambia el email de la cuenta a un email que no sea público (evita `admin@gmail.com`)

---

## Post-deploy

Después de aplicar los cambios de código (FASE 2) y desplegar:

```bash
cd /home4/bluelata/public_html/website_c67adca2
php artisan config:cache
php artisan route:cache
php artisan optimize:clear
```

### Checklist de verificación final:

- [ ] `curl -sD - "$BASE/api/user" | head -20` → HTTP 401, no stack trace
- [ ] `curl -sI "$BASE/storage/logs/laravel.log"` → HTTP 403/404
- [ ] `curl -sI "$SITE/" | grep -iE "strict-transport|x-frame|x-content-type|content-security|referrer-policy"` → todas presentes
- [ ] `curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/users/byId/1"` → HTTP 403
- [ ] `curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/users/admin/get_pendings"` → HTTP 403
- [ ] `curl -s -X POST "$BASE/api/bookings/cancel/1" -H "Authorization: Bearer $TOKEN" -d '{}'` → HTTP 403 si la reserva no es tuya
- [ ] `curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/notices/byId/1" | grep -E '"user"|"views"'` → no debe mostrar user completo ni views si no eres admin
- [ ] 2FA activado en cuenta admin
- [ ] Credenciales expuestas rotadas (DB, Pusher, Firebase, etc.)