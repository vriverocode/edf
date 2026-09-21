# Avances - 20 de Septiembre 2026

## 1. Fix MonthlyBillsController - Variable undefined

**Problema:** `$monthlyBill` era creada dentro del closure de `DB::transaction` pero se usaba fuera para el `dispatch` del job.

**Solución:** Declarar `$monthlyBill = null` fuera del closure y pasarla por referencia (`&$monthlyBill`) en el `use`.

**Archivo:** `app/Http/Controllers/Api/MonthlyBillsController.php:170-172`

```php
$monthlyBill = null;
DB::transaction(function () use ($expensesData, $monthName, $validated, &$createdExpenses, &$monthlyBill) {
```

---

## 2. Fix duplicación de expenses al crear MonthlyBill

**Problema:** Al crear un presupuesto mensual, se creaban los expenses 3 veces.

**Causa:** El frontend enviaba los gastos seleccionados desde el modal Y el backend también ejecutaba el auto-load de templates del presupuesto anual activo cuando `$expensesData` estaba vacío.

**Solución confirmada por usuario:** Se resolvió (detalles del fix por parte del usuario).

---

## 3. MonthlyBillsDetails - Eliminar presupuesto base

**Problema:** La vista mostraba "Presupuesto base mantenimiento" que ya no era relevante.

**Cambios en** `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue`:

### 3.1 Eliminada fila de la tabla (líneas 165-168 originales)
```vue
<!-- ELIMINADO -->
<tr>
  <td>Presupuesto base mantenimiento</td>
  <td>{{ Number(bill.monthly_budget).toFixed(2) }}</td>
</tr>
```

### 3.2 Reemplazado "Base mantenimiento" por "Consumo agua (m³)" en resumen
```vue
<!-- ANTES -->
<div class="bg-grey-2 rounded-lg py-2 px-2 text-center">
  <div class="text-grey-6">Base mantenimiento</div>
  <div class="text-subtitle2 text-bold">S/ {{ Number(bill.monthly_budget).toFixed(2) }}</div>
</div>

<!-- DESPUÉS -->
<div class="bg-blue-1 rounded-lg py-2 px-2 text-center">
  <div class="text-grey-6">Consumo agua (m³)</div>
  <div class="text-subtitle2 text-bold text-blue-9">{{ bill.total_water_consumption_m3 ?? '-' }}</div>
</div>
```

### 3.3 Reemplazado "Agua común" por "Precio por m³"
```vue
<!-- ANTES -->
<div class="bg-grey-2 rounded-lg py-2 px-2 text-center">
  <div class="text-grey-6">Agua común</div>
  <div class="text-subtitle2 text-bold">S/ {{ commonWaterCost.toFixed(2) }}</div>
</div>

<!-- DESPUÉS -->
<div class="bg-blue-1 rounded-lg py-2 px-2 text-center">
  <div class="text-grey-6">Precio por m³</div>
  <div class="text-subtitle2 text-bold text-blue-9">S/ {{ Number(bill.water_price_per_m3).toFixed(4) }}</div>
</div>
```

### Resumen del mes final
| Cuadro | Valor |
|--------|-------|
| Consumo agua (m³) | `bill.total_water_consumption_m3` |
| Gastos del mes | `expensesTotal` |
| Precio por m³ | `bill.water_price_per_m3` |
| **Total del mes** | `bill.total_maintenance_budget` |

---

## Archivos modificados en esta sesión

| Archivo | Cambios |
|---------|---------|
| `app/Http/Controllers/Api/MonthlyBillsController.php` | Fix `$monthlyBill` por referencia en closure |
| `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue` | Eliminado presupuesto base, mostrado consumo agua + precio unitario |
