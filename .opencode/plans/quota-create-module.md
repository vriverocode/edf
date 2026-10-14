# Plan: Módulo admin de creación de cuotas (admin + super-admin)

## Requisitos (confirmados)

- **Acceso**: solo `admin` (rol_id 1) y `super-admin` (rol_id 8).
- **Formulario**: seleccionar usuario → se traen sus departamentos como filas; mes y año globales; por cada departamento: monto del agua y monto total editables + botón para agregar gastos (DepartmentCharge: daño, multa).
- **Submit único**: crea cuotas para todos los departamentos que se puedan; **omite** los que ya tengan cuota ese mes/año y los reporta.
- **Fórmula**: `amount = total_ingresado + extra_amount` (los gastos se suman encima); `maintenance_amount = total_ingresado − water_amount`.
- **Gastos**: se crean como `DepartmentCharge` completo (installments, start_month/year) y se vinculan a la cuota vía pivote `charge_quota`.

---

## Backend

### 1. Ruta — `routes/api.php`

Dentro del grupo `quotas` (`:254-271`), junto a los writes admin:

```php
Route::post('/admin/batch', [QuotaController::class, 'storeBatch'])
    ->middleware('role:admin,super-admin', 'throttle:write');
```

### 2. `QuotaController::storeBatch()` — nuevo método

**Validación** (patrón `Validator::make` del controller):

| Campo | Reglas |
|---|---|
| `user_id` | required, exists:users,id |
| `month` | required, integer, between:1,12 |
| `year` | required, integer, min:2020 |
| `departments` | required, array, min:1 |
| `departments.*.departament_id` | required, exists:departaments,id |
| `departments.*.water_amount` | nullable, numeric, min:0 |
| `departments.*.total_amount` | required, numeric, min:0 |
| `departments.*.charges` | nullable, array |
| `departments.*.charges.*.description` | required, string, max:500 |
| `departments.*.charges.*.total_amount` | required, numeric, min:0.01 |
| `departments.*.charges.*.installments` | required, integer, min:1 |

Además: validar que cada `departament_id` pertenezca al usuario (owner `user_id` o tenant activo vía `PeoplesXDepartaments`), patrón de `DepartamentController::getDepartmentsByOwnerId`.

**Lógica** (transacción + skip existentes):

```php
DB::beginTransaction();
foreach ($request->departments as $dept) {
    // 1. Skip si ya existe cuota (departament_id + month + year)
    $exists = Quota::where('departament_id', $dept['departament_id'])
        ->where('month', $month)->where('year', $year)->exists();
    if ($exists) { $skipped[] = [...]; continue; }

    // 2. Crear DepartmentCharges (si vienen): start_month/year = periodo actual,
    //    monthly_amount = total/installments, status 1
    $extraAmount = sum(monthly_amount de los charges creados);

    // 3. Crear Quota:
    $water = $dept['water_amount'] ?? 0;
    $total = $dept['total_amount'];
    $maintenance = round($total - $water, 2);
    $amount = round($total + $extraAmount, 2);
    Quota::create([
        'departament_id', 'peoples_x_departments_id' (tenant pivot si aplica,
            vía MonthlyQuotaService::findActiveTenantPivotId),
        'month', 'year',
        'due_date' => Carbon::create($year, $month, 1)->endOfMonth(),
        'maintenance_amount' => $maintenance,
        'water_amount' => $water,
        'extra_amount' => $extraAmount,
        'amount' => $amount,
        'number' => 'A'.substr($departament->number, -3).'-'.$month.rand(1000, 9999),
        'type' => $departament->type == 1 ? 1 : 2,
        'description' => 'Cuota manual: '.$labelMonth($month).' '.$year,
        'status' => 1,
    ]);

    // 4. Vincular charges vía charge_quota (installment_number = 1, periodo actual)
}
DB::commit();
// Respuesta: { created: N, skipped: [{departament_id, number, reason}] }
```

Usar `MonthlyQuotaService::findActiveTenantPivotId()` (estático y público) para `peoples_x_departments_id`.

**Nota deduplicación**: usar columna `year` (migración `2026_09_09`) y NO `whereYear('due_date', ...)` — consistencia en este endpoint nuevo.

### 3. Notas de diseño

- No se envía notificación realtime al cliente (cuota creada manualmente por admin; el cliente la verá en su lista).
- No se toca `WaterReading` (el monto de agua es manual, no se asocia lectura).

---

## Frontend

### 4. Vista — `frontend/src/resources/view/admin/Quotas/quotaCreateForm.vue`

Patrón: `departmentChargeForm.vue` (q-form + q-select filtrable + Notify) + `quotaEditForm.vue` (inputs monetarios con máscara `###.###.###,##` reverse-fill-mask, clase `form__inputsR`).

**Sección 1 — Datos globales:**
- `q-select` usuario: carga vía `usersStore.getUsersOptions()`; filtrable con `use-input @filter`; al seleccionar → `apartmentStore.getDepartmentsByOwner(userId)` trae departamentos.
- `q-select` mes (Enero–Diciembre), `q-input` año (number).

**Sección 2 — Filas por departamento** (una por cada depto del usuario):
- Número de departamento (label).
- Input "Monto total" (monetario, obligatorio).
- Input "Monto del agua" (monetario, opcional, default 0).
- Subtotal calculado en vivo: `total + sum(charges.monthly_amount)`.
- Botón "Agregar gasto" → `q-dialog` con formulario: descripción, monto total, cuotas (installments). Se agregan a un array local `charges` del departamento. Muestra lista de gastos agregados con botón de eliminar.

**Sección 3 — Submit:**
- Botón "Crear cuotas" → `quotaStore.createQuotasBatch(payload)`.
- Resultado: `Notify` con "X cuotas creadas, Y omitidas (ya existían)". Si hay omitidas, mostrar lista de números de depto omitidos.

### 5. Store — `frontend/src/resources/services/store/quota.store.js`

Nueva acción:

```js
async createQuotasBatch(data) {
  return ApiService.post('/api/quotas/admin/batch', data)
}
```

### 6. Ruta — `frontend/src/resources/routes/index.js`

```js
{
  path: '/admin/quotas/create',
  component: () => import('@/view/admin/Quotas/quotaCreateForm.vue'),
  name: 'quotaCreateForm',
  beforeEnter: [auth, role],
  meta: {
    title: 'Bienvenido',
    pagTitle: 'Crear cuotas',
    roles: ['admin', 'super-admin'],
    depth: 3,
  },
}
```

### 7. Tile en menú — `frontend/src/resources/view/admin/Quotas/quotasMenu.vue`

Agregar entrada al array `menu`:

```js
{
  title: 'Crear cuotas',
  link: '/admin/quotas/create',
  icon: cuotas,  // reutilizar imagen existente
  roles: [1, 8],  // admin y super-admin
},
```

**Nota**: el tile actual "Cuotas de mantenimiento" solo tiene `roles: [1]` — el nuevo tile lleva `[1, 8]` para incluir super-admin. No se modifica el existente (fuera de alcance).

---

## Archivos a tocar

| Archivo | Acción |
|---|---|
| `routes/api.php` | Agregar ruta POST `/quotas/admin/batch` |
| `app/Http/Controllers/Api/QuotaController.php` | Nuevo método `storeBatch()` |
| `frontend/src/resources/services/store/quota.store.js` | Nueva acción `createQuotasBatch` |
| `frontend/src/resources/view/admin/Quotas/quotaCreateForm.vue` | **Nuevo archivo** — formulario |
| `frontend/src/resources/routes/index.js` | Nueva ruta `/admin/quotas/create` |
| `frontend/src/resources/view/admin/Quotas/quotasMenu.vue` | Agregar tile "Crear cuotas" |

## Verificación

1. `./vendor/bin/pint` en PHP.
2. `php -l` en QuotaController.
3. Flujo manual: crear cuotas para un usuario con 2 deptos, uno sin cuota y otro con → verificar que solo se crea el primero y el reporte indica el omitido; verificar que los DepartmentCharges se crean y vinculan vía `charge_quota`; verificar que `amount = total + extra`.
