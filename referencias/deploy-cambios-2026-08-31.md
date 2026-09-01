# Deploy - Cambios 31 Agosto 2026

## Resumen de cambios

### 1. Buscador por nombre mejorado (quotas admin)
- **Archivo:** `app/Http/Controllers/Api/QuotaController.php` — `owner_search` ahora busca por nombre de propietario Y de inquilino/responsable (`orWhereHas('responsiblePivot.user')`)
- **Archivo:** `frontend/.../quotasMaintenanceMonthDetail.vue` — placeholder cambiado a "Buscar por nombre..."

### 2. Agrupación por usuario responsable (quotas admin)
- **Archivo:** `app/Models/Quota.php` — `groupConsolidatedByOwner()` reagrupado por `responsibleUserId_month_year` en lugar de `departamentId_month_year`. Si `tenant_pays_quota=true` agrupa por inquilino, si `false` por propietario
- **Archivo:** `frontend/.../quotasMaintenanceMonthDetail.vue` — muestra `quota.responsible_name` en lugar de `quota.owner_name`
- **Resultado:** Si un propietario paga 3 departamentos → 1 sola tarjeta. Si un inquilino paga 2 deptos de distintos propietarios → 1 sola tarjeta

### 3. Rediseño details de presupuesto mensual
- **Archivo:** `frontend/.../monthlyBillsDetails.vue` — sección "DATOS DE AGUA" movida arriba (cards compactas azules), sección "RESUMEN DEL MES" agregada abajo (3 cards fila: base, gastos, agua + card verde total)

### 4. Modal de gastos reutilizable en edición de presupuesto
- **Archivo:** `frontend/.../monthlyBillsEditForm.vue` — modal inline reemplazado por componente `includeExpensesModal` (checkboxes, seleccionar todos, total seleccionado). Removidos `useExpenseStore`, `expenseForm`, `providerOptions`, `categoryOptions`, `loadFormOptions`, `loadUnassignedExpenses`

### 5. Vista de detalles de gasto
- **Archivo:** `frontend/src/resources/view/admin/Expenses/expenseDetails.vue` — **NUEVO** vista con información del gasto, monto, fechas, descripción, documento adjunto (preview imagen/icono PDF + descargar), presupuesto asociado
- **Archivo:** `frontend/src/resources/routes/index.js` — ruta `/admin/expenses/details/:id`
- **Archivo:** `frontend/.../expensesList.vue` — opción "Ver detalles" en menú de opciones
- **Backend:** `ExpenseController::show()` y `GET /api/expenses/byId/{id}` ya existían
