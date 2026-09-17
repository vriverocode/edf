# Avances 17 de Septiembre 2026

## 1. Corrección "Saldo a favor" en payMaintenanceList.vue

**Problema:** El badge "Saldo a favor: S/." aparecía en TODOS los pagos, incluso cuando el pago fue por el monto exacto de la cuota.

**Causa:** La función `getCreditForPay(pay)` consultaba el saldo crediticio global del departamento en la tabla `credit_balances`, no el monto sobrepagado en ese pago específico.

**Solución:** Reemplazar la lógica para usar `pay.overpayment_amount` directamente:

```js
const getCreditForPay = (pay) => {
  return pay.overpayment_amount || 0
}
```

**Archivos modificados:**
- `frontend/src/resources/view/admin/Pays/payMaintenanceList.vue`
  - Eliminado `creditBalances` ref
  - Eliminado `fetchCreditBalances()` y su llamada
  - Simplificado `getCreditForPay()` para usar `overpayment_amount`

---

## 2. Sistema de Gastos Únicos por Departamento

Feature completa para cobrar gastos extraordinarios a departamentos específicos en cuotas mensuales.

### 2.1 Base de datos

**Nuevas tablas:**

| Tabla | Descripción |
|-------|-------------|
| `department_charges` | Cargos extras a departamentos (id, departament_id, expense_id, description, total_amount, monthly_amount, installments, paid_installments, status, start_month, start_year, deleted_at) |
| `charge_quota` | Pivote entre cargos y cuotas (department_charge_id, quota_id, installment_number) |

**Tablas modificadas:**

| Tabla | Columna agregada |
|-------|------------------|
| `quotas` | `extra_amount` decimal(15,2) default 0 |
| `expenses` | `departament_id` FK nullable |

**Migraciones:**
- `2026_09_17_000001_create_department_charges_table.php`
- `2026_09_17_000002_create_charge_quota_table.php`
- `2026_09_17_000003_add_extra_amount_to_quotas_table.php`
- `2026_09_17_000004_add_departament_id_to_expenses_table.php`

### 2.2 Backend

**Nuevo modelo:** `app/Models/DepartmentCharge.php`
- Relaciones: `departament()`, `expense()`, `quotas()`
- Scopes: `active()`, `applicableForPeriod($month, $year)`
- Soft deletes

**Modelos modificados:**
- `Expense.php`: +`departament_id` en fillable, +relationships `departament()`, `departmentCharges()`
- `Quota.php`: +`extra_amount` en fillable/casts, +relationship `departmentCharges()`

**Nuevo controller:** `app/Http/Controllers/Api/DepartmentChargeController.php`
- `index()` — Listar con filtros
- `show()` — Detalle con relaciones
- `store()` — Crear cargo
- `update()` — Editar cargo
- `destroy()` — Soft delete
- `history()` — Historial de cuotas pagadas del cargo
- `availableExpenses()` — Gastos disponibles para vincular
- `updateExistingQuotas()` — Actualiza cuotas existentes al crear/editar
- `removeExtrasFromQuotas()` — Limpia extras al eliminar

**Modificado:** `app/Services/MonthlyQuotaService.php`
- `makeQuotaOfMonth()`: Calcula `extra_amount` para el departamento
- Nuevo método `getExtraAmountForDepartment()`: Suma cargos activos
- Nuevo método `linkChargesToQuota()`: Vincula cargos a cuota en pivote

**Modificado:** `app/Http/Controllers/Api/PayController.php`
- `validatePayment()`: Llama a `incrementChargeInstallments()` al aprobar pago
- Nuevo método `incrementChargeInstallments()`: Incrementa `paid_installments` y marca como pagado si termina

**Rutas API agregadas** (`routes/api.php`):
```
GET    /api/department-charges              → Listar
POST   /api/department-charges              → Crear
GET    /api/department-charges/{id}         → Detalle
POST   /api/department-charges/{id}         → Actualizar
DELETE /api/department-charges/{id}         → Eliminar
GET    /api/department-charges/{id}/history → Historial
GET    /api/department-charges/available-expenses → Gastos disponibles
```

### 2.3 Frontend

**Nuevo store:** `services/store/departmentCharge.store.js`
- Acciones: `getDepartmentCharges`, `getDepartmentCharge`, `getChargeHistory`, `createDepartmentCharge`, `updateDepartmentCharge`, `deleteDepartmentCharge`, `getAvailableExpenses`

**Nuevas vistas:**
- `view/admin/DepartmentCharges/departmentChargeList.vue` — Lista de cargos con filtros
- `view/admin/DepartmentCharges/departmentChargeForm.vue` — Formulario con selector de gasto vinculado (type 2 = Extraordinario)

**Modificada:** `view/client/Quotas/viewQuota.vue`
- Agregada fila "Cargo extra" en el desglose de cuota (visible cuando `extra_amount > 0`)

**Modificada:** `view/admin/financePage.vue`
- Nuevo ítem de menú "Cargos Depto." con icono `cuotas-especiales.png`

**Rutas Vue agregadas** (`routes/index.js`):
```
/admin/department-charges              → Lista
/admin/department-charges/create       → Crear
/admin/department-charges/:id/edit     → Editar
```

### 2.4 Flujo del sistema

```
1. Admin registra gasto al proveedor (expenses, type 2 = Extraordinario)
2. Admin crea cargo extra al departamento → monthly_amount = total / installments
3. Si existen cuotas futuras → se actualizan con el extra
4. MonthlyQuotaService genera cuotas → suma extra_amount automáticamente
5. Al pagar cuota → paid_installments se incrementa
6. Al completar todas las cuotas → status cambia a "Pagado"
```

---

## 3. Seguridad: Protección de rol_id

**Vulnerabilidades encontradas:**

| # | Severidad | Problema |
|---|-----------|----------|
| 1 | CRITICAL | Parameter mismatch: `store()` lee `rol_id` pero usa `idRol` |
| 2 | HIGH | Sin validación del valor de `idRol` |
| 3 | HIGH | `update()` acepta `rol_id` sin validación |
| 4 | MEDIUM | Lógica de autorización no protegía super-admin |

**Correcciones en** `app/Http/Controllers/Api/UserController.php`:

### Fix 1: `validateFieldsFromInput()` — Validación de idRol
```php
'idRol' => ['required', 'integer', 'in:1,2,3,4,5,6,7,8'],
```

### Fix 2: `store()` — Autorización unificada
```php
$creatorRole = request()->user()->rol_id;
$requestedRole = $request->idRol;

// Solo admin y super-admin pueden crear usuarios
if (! in_array($creatorRole, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
    return 403;
}

// Solo super-admin puede crear admins o super-admins
if (in_array($requestedRole, [Rol::ADMIN, Rol::SUPER_ADMIN]) && $creatorRole !== Rol::SUPER_ADMIN) {
    return 403;
}
```

### Fix 3: `update()` — Validación + Autorización
```php
'rol_id' => ['nullable', 'integer', 'in:1,2,3,4,5,6,7,8'],
```
```php
if ($request->has('rol_id')) {
    if (! in_array($currentUser->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
        return 403;
    }
    if (in_array($request->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN]) && $currentUser->rol_id !== Rol::SUPER_ADMIN) {
        return 403;
    }
}
```

### Reglas de seguridad resultantes

| Acción | Requisito |
|--------|-----------|
| Crear usuario | Admin o Super-admin |
| Crear admin/super-admin | Solo Super-admin |
| Cambiar rol | Admin o Super-admin |
| Asignar rol admin/super-admin | Solo Super-admin |
| Valor de rol | Solo 1-8 (allowlist) |

---

## 4. Importación de saldos a favor desde Excel

**Comando:** `php artisan import:credit-balances`

- Lee la hoja `DEVOL PARA SEPT. 26` de `referencias/pagos_agosto.xlsm`
- Crea registros en `credit_balances` y logs en `credit_transactions`
- Suma montos de Junio + Julio + Agosto por departamento
- Maneja filas duplicadas y mapeo de códigos (101 → DPT-101, ESTA-003 → EST-003)
- **Resultado:** 195 departamentos con saldo, total S/. 15,149.80

**Archivo creado:**
- `app/Console/Commands/ImportCreditBalances.php`

---

## 5. Persistencia de filtros en payMaintenanceList.vue

- Agregado `syncToUrl()` — serializa filtros (status, search, date_from, date_to) al query string
- Agregado `restoreFromQuery()` — restaura filtros desde URL al montar
- Fix: removido `emit-value` del `q-select` para que `filters.status` sea objeto `{ label, value }`
- URL se actualiza al filtrar: `/admin/pay/list?status=2&search=PAC101&page=1`

**Archivo modificado:** `frontend/src/resources/view/admin/Pays/payMaintenanceList.vue`

---

## 6. Filtros + persistencia en payBookingList.vue

- Mismo patrón que payMaintenanceList: `syncToUrl()` + `restoreFromQuery()`
- Nuevos filtros: estado del pago, búsqueda (nombre/área común), fechas desde/hasta
- Persistencia en URL query params

**Archivo modificado:** `frontend/src/resources/view/admin/Pays/payBookingList.vue`

---

## 7. Vista de saldos a favor (admin)

### Backend
- `CreditService::getAllWithBalance()` — retorna deptos con balance > 0 con owner
- `CreditService::getAllTransactions()` — historial global paginado con filtros
- `CreditController::listAll()` — `GET /api/admin/credits`
- `CreditController::listAllTransactions()` — `GET /api/admin/credits/transactions`
- Rutas admin bajo middleware `role:admin,super-admin`

### Frontend
- **creditsList.vue** — Lista de deptos con saldo activo, badge saldo total, búsqueda
- **creditDetail.vue** — Saldo actual + historial de transacciones cronológico
- Store: `getAllCredits()`, `getAllCreditTransactions()` en pay.store.js
- Rutas: `/admin/credits` (depth 3), `/admin/credits/:id` (depth 4)
- Navegación: ítem "Saldos a favor" en payMenu.vue, path en navbarAdmin.vue

**Archivos creados:**
- `frontend/src/resources/view/admin/Credits/creditsList.vue`
- `frontend/src/resources/view/admin/Credits/creditDetail.vue`

**Archivos modificados:**
- `app/Services/CreditService.php`
- `app/Http/Controllers/Api/CreditController.php`
- `routes/api.php`
- `frontend/src/resources/services/store/pay.store.js`
- `frontend/src/resources/routes/index.js`
- `frontend/src/resources/view/admin/Pays/payMenu.vue`
- `frontend/src/resources/components/layout/navbarAdmin.vue`

---

## 8. Crear gastos template desde annualBudgetForm

### Migration
- `2026_09_17_194718_update_service_categories_names.php` — renombra las 6 categorías:
  1. Servicios Básicos
  2. Admin
  3. Mantenimientos Preventivos
  4. Gastos Operativos / Contingencias
  5. Materiales Consumibles
  6. Otros Gastos Ordinarios

### Modal
- `createExpenseTemplateModal.vue` — formulario con nombre, monto y categoría (select desde API)
- Patrón igual a createProviderModal: q-dialog > q-card > campos + botones

### Formulario
- Eliminado enfoque inline (newExpenses)
- Botón "Nuevo gasto" en Step 2 abre el modal
- Gasto creado se agrega a `selectedExpenses[]` y se envía al backend

**Archivos creados:**
- `database/migrations/2026_09_17_194718_update_service_categories_names.php`
- `frontend/src/resources/components/finance/createExpenseTemplateModal.vue`

**Archivos modificados:**
- `frontend/src/resources/view/admin/Budget/annualBudgetForm.vue`
