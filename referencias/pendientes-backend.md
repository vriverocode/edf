# Pendientes Backend

Tareas que debes implementar en el backend (Laravel) para que funcionalidades del frontend funcionen correctamente.

---

## 1. POST /api/pays/refund — Registrar devolución

**Relacionado con:** #7 (flujo de devolución en reservas)

**Ruta actual:** No existe

**Ruta necesaria:**
```
POST /api/pays/refund
```

**Controlador:** `app/Http/Controllers/Api/PayController.php`

**Qué debe hacer:**
- Recibir `{ booking_id, amount, reason }`
- Crear un registro de devolución (nuevo modelo `Refund` o usar `Pays` con tipo devolución)
- Actualizar estado del pago original a "reembolsado"
- Disparar notificación al usuario

**Body esperado:**
```json
{
  "booking_id": 123,
  "amount": 50.00,
  "reason": "Cancelación de reserva"
}
```

**Ruta ya referenciada en frontend:**
- `validatePay.vue` línea ~249: `await ApiService.post('/api/pays/refund', payload)`

---

## 2. POST /api/quotas/generate — Generar cuotas de mantenimiento

**Relacionado con:** #30

**Ruta actual:** No existe

**Ruta necesaria:**
```
POST /api/quotas/generate?month=X&year=Y
```

**Controlador:** `app/Http/Controllers/Api/QuotaController.php`

**Qué debe hacer:**
- Tomar el presupuesto mensual del mes/año indicado
- Calcular cuota para cada propietario según su porcentaje de participación
- Crear registros en tabla `quotas` para cada unidad
- Retornar resumen de cuotas generadas (cantidad, monto total)

**Body:** No requiere body, parámetros vía query string

**Ruta ya referenciada en frontend:**
- `quota.store.js`: `ApiService.post('/api/quotas/generate?month=' + month + '&year=' + year)`

---

## 3. GET/PUT/DELETE /api/providers — CRUD completo de proveedores

**Relacionado con:** #31

**Ruta actual:** Solo existe `POST /api/providers`

**Rutas necesarias:**

| Método | Ruta | Acción |
|--------|------|--------|
| GET | `/api/providers` | Listar proveedores (con paginación y búsqueda) |
| GET | `/api/providers/byId/{id}` | Obtener un proveedor por ID |
| POST | `/api/providers/u/{id}` | Actualizar proveedor |
| DELETE | `/api/providers/d/{id}` | Eliminar proveedor |

**Controlador:** `app/Http/Controllers/Api/ProviderController.php`

**Métodos necesarios en el controlador:**

```php
public function index(Request $request)
{
    // Devolver lista paginada con search por nombre/RUC
    // Incluir relación service_category
}

public function show($id)
{
    // Devolver un proveedor por ID
}

public function update(Request $request, $id)
{
    // Actualizar proveedor
}

public function destroy($id)
{
    // Eliminar proveedor
}
```

**Rutas ya referenciadas en frontend:**
- `provider.store.js`:
  - `ApiService.get('/api/providers' + query)`
  - `ApiService.get('/api/providers/byId/' + id)`
  - `ApiService.post('/api/providers/u/' + id, payload)`
  - `ApiService.delete('/api/providers/d/' + id)`

---

## 4. POST/DELETE /api/service-categories — CRUD completo de categorías

**Relacionado con:** #32

**Ruta actual:** Solo existe `GET /api/service-categories` y `POST /api/service-categories`

**Rutas necesarias:**

| Método | Ruta | Acción |
|--------|------|--------|
| POST | `/api/service-categories/u/{id}` | Actualizar categoría |
| DELETE | `/api/service-categories/d/{id}` | Eliminar categoría |

**Controlador:** `app/Http/Controllers/Api/ServiceCategoryController.php`

**Métodos necesarios:**

```php
public function update(Request $request, $id)
{
    // Actualizar categoría
}

public function destroy($id)
{
    // Eliminar categoría (verificar que no tenga proveedores asociados)
}
```

**Rutas ya referenciadas en frontend:**
- `serviceCategory.store.js`:
  - `ApiService.post('/api/service-categories/u/' + id, payload)`
  - `ApiService.delete('/api/service-categories/d/' + id)`

---

## 5. GET /api/expenses — Incluir attachment_url en listado

**Relacionado con:** #33

**Ruta actual:** `GET /api/expenses` ya existe

**Qué debe cambiar:**
- Asegurar que la respuesta del método `index` del `ExpenseController` incluya el campo `attachment_url` en cada gasto
- El frontend muestra el icono de adjunto solo cuando `expense.attachment_url` tiene valor

**Archivo:** `app/Http/Controllers/Api/ExpenseController.php`

---

## 6. GET /api/expenses — Aceptar filtros provider_id, category_id, date_from, date_to

**Relacionado con:** #34

**Ruta actual:** `GET /api/expenses` ya acepta `month`, `year`, `status`

**Qué debe cambiar:**
- El método `index` del `ExpenseController` debe aceptar y aplicar los siguientes query params:
  - `provider_id` — filtrar por proveedor
  - `category_id` — filtrar por categoría (si el modelo Expense se relaciona con ServiceCategory, filtrar por esa relación)
  - `date_from` — fecha de emisión desde
  - `date_to` — fecha de emisión hasta

**Archivo:** `app/Http/Controllers/Api/ExpenseController.php`

---

## 7. POST/PUT /api/notices — Aceptar segmentación (segment_type + segment_ids)

**Relacionado con:** #9

**Rutas actuales:** `POST /api/notices` y `POST /api/notices/{id}` ya existen

**Qué debe cambiar:**

**Store (crear):**
- Aceptar campos `segment_type` (string: all, tower, floor, department, user)
- Aceptar campo `segment_ids` (array de IDs)
- Guardar en base de datos (columna JSON o tabla pivote)

**Update:**
- Aceptar los mismos campos para actualizar segmentación

**Show:**
- Devolver `segment_type` y `segment_ids` en la respuesta

**Además:** Al enviar notificaciones push (crear/actualizar), filtrar los destinatarios según la segmentación:
- `all`: todos los usuarios
- `tower`: solo usuarios cuyas unidades están en esa torre
- `floor`: solo usuarios cuyas unidades están en ese piso
- `department`: solo el propietario de ese departamento
- `user`: solo el usuario específico

**Posible migración necesaria:**
```php
Schema::table('notices', function (Blueprint $table) {
    $table->string('segment_type')->default('all')->after('status');
    $table->json('segment_ids')->nullable()->after('segment_type');
});
```

**Archivos:**
- `app/Http/Controllers/Api/NoticeController.php`

**Nota:** El frontend ya envía los campos `segment_type` y `segment_ids[]`, solo falta que el backend los guarde y los use para filtrar notificaciones.

---

## 8. GET /api/reports/bookings — Aceptar include_cancelled

**Relacionado con:** #10

**Ruta actual:** `GET /api/reports/bookings` ya existe

**Qué debe cambiar:**
- El método `bookings` del `ReportController` debe aceptar el query param `include_cancelled`
- Por defecto (`include_cancelled=0` o ausente), excluir reservas canceladas
- Cuando `include_cancelled=1`, incluirlas

**Archivo:** `app/Http/Controllers/Api/ReportController.php`

---

## 9. Bloques horarios en áreas comunes (nuevo feature)

**Relacionado con:** #4 (bloqueado)

**Descripción:**
- Agregar columna `time_slots` (JSON) a la tabla `comun_areas`
- Modificar controlador para aceptar y retornar los bloques horarios
- Cada bloque: `{ "day": 1, "start": "06:00", "end": "08:00" }`

**Migración sugerida:**
```php
Schema::table('comun_areas', function (Blueprint $table) {
    $table->json('time_slots')->nullable()->after('hora_fin');
});
```

---

## 10. Mantenimiento debe bloquear reservas automáticamente

**Relacionado con:** #5 (bloqueado)

**Descripción:**
- Al crear un mantenimiento (`POST /api/maintenances`), el backend debe:
  1. Buscar reservas existentes en la misma área, fecha y horario
  2. Cancelarlas automáticamente (usando `POST /bookings/cancel-maintenance/{id}`)
  3. Notificar a los usuarios afectados

---

## Resumen de endpoints

| Endpoint | Método | Estado | Prioridad |
|----------|--------|--------|-----------|
| `/api/pays/refund` | POST | ❌ No existe | 🔴 Alta |
| `/api/quotas/generate` | POST | ❌ No existe | 🟠 Media |
| `/api/providers` | GET | ❌ No existe | 🟠 Media |
| `/api/providers/byId/{id}` | GET | ❌ No existe | 🟠 Media |
| `/api/providers/u/{id}` | POST | ❌ No existe | 🟠 Media |
| `/api/providers/d/{id}` | DELETE | ❌ No existe | 🟠 Media |
| `/api/service-categories/u/{id}` | POST | ❌ No existe | 🟠 Media |
| `/api/service-categories/d/{id}` | DELETE | ❌ No existe | 🟠 Media |
| `/api/expenses` (attachment_url) | GET | ⚠️ Existe, falta campo | 🟠 Media |
| `/api/expenses` (filtros) | GET | ⚠️ Existe, faltan filtros | 🟡 Baja |
| `/api/notices` (segmentación) | POST/PUT | ⚠️ Existe, falta lógica | 🟠 Media |
| `/api/reports/bookings` (include_cancelled) | GET | ⚠️ Existe, falta flag | 🟠 Media |
| Bloques horarios (#4) | — | ❌ No existe | 🟠 Media |
| Mantenimiento bloquea reservas (#5) | — | ❌ No existe | 🔴 Alta |
