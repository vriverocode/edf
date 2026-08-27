# Cambios: Lecturas de Áreas Comunes + Costo Agua Común

**Fecha:** 2026-08-28  
**Commit:** `c2e4615`

---

## 1. Migraciones

### `2026_08_28_000001_add_is_common_to_water_readings_table.php`
- `water_readings.departament_id` → nullable (antes era required)
- Nueva columna `is_common` (boolean, default false)

### `2026_08_28_000002_add_common_water_consumption_to_monthly_bills_table.php`
- Nueva columna `monthly_bills.common_water_consumption_m3` (decimal 10,2, nullable)

---

## 2. Backend

### Model `WaterReading.php`
- `is_common` agregado a `$fillable`
- Nuevo accessor `getConsumptionAttribute()` → `round(current - previous, 3)` (ya existía)
- `$appends` incluye `consumption`
- Nuevos scopes: `scopeCommon()`, `scopeForDepartment()`

### Model `MonthlyBills.php`
- `common_water_consumption_m3` agregado a `$fillable` y `$casts`

### Controller `WaterReadingController.php`

**`store()`:**
- Validación condicional: `departament_id` required si `is_common=false`, nullable si `is_common=true`
- Para common: unicidad por `(is_common, month, year)` — solo una lectura común por mes
- Para department: unicidad por `(departament_id, month, year)` — como antes
- Payload incluye `is_common`

**`update()`:**
- Mismas reglas condicionales que store

**`index()`:**
- Nuevo filtro `is_common` opcional
- `orderBy('is_common', 'asc')` para que departments aparezcan primero

**`consumptionByMonth()`:**
- Retorna `is_common` en cada lectura
- Nombre: `"Área Común"` cuando `is_common=true`

### Controller `MonthlyBillsController.php`

**`store()` y `update()`:**
- Nuevo campo validación: `common_water_consumption_m3` (nullable, numeric, min:0)
- Cálculo del total:
  ```
  common_water_cost = common_water_consumption_m3 × water_price_per_m3
  calculatedTotal = monthly_budget + expenses + common_water_cost
  ```
- Error message actualizado: "Presupuesto base + Gastos + Costo agua común"

### Service `BillInvoiceService.php`
- `buildInvoiceData()` retorna:
  - `commonWaterConsumption` = `$monthlyBill->common_water_consumption_m3`
  - `commonWaterCost` = `consumption × waterPricePerM3`

### Template `invoice.blade.php`
- Nueva sección "COSTO AGUA ÁREAS COMUNES" (solo si `commonWaterConsumption > 0`)

---

## 3. Frontend

### `monthlyBillsForm.vue`
- Nuevo campo `common_water_consumption_m3` en formData
- Nuevo computed `commonWaterCost` = `consumption × price`
- `calculatedTotal` = `budget + expenses + commonWaterCost`
- Precio m³ calculado: `bill / (dept_consumption + common_consumption)`
- Template: campo "Consumo áreas comunes (m³)" + banner con costo calculado
- Submit incluye `common_water_consumption_m3` en payload

### `waterReadingForm.vue`
- Nuevo toggle `formData.is_common`
- Cuando activo: oculta selector de departamento
- Payload: envía `is_common` y no envía `departament_id`

### `waterReadingEditForm.vue`
- Nuevo toggle `formData.is_common`
- `loadReading()` pre-populate `is_common` desde la API
- Toggle oculta selector de departamento

### `waterReadingsList.vue`
- Badge "Área Común" en ámbar cuando `r.is_common`

### `waterReadingDetails.vue`
- Muestra "Área Común" en vez de "Dpt: null"
- Oculta sección de propietario cuando `is_common`

### `waterReadingsModal.vue` (presupuesto mensual)
- Ya mostraba "Área Común" porque `consumptionByMonth()` lo retorna

---

## 4. Flujo numérico

```
Presupuesto base:        S/ 5,000
Gastos incluidos:        S/   500
Consumo dept:            5,000 m³
Consumo común:           1,000 m³
Total consumo:           6,000 m³
Factura agua:            S/ 6,000
─────────────────────────────────
Precio m³ = 6,000/6,000 = S/ 1.00
Costo agua común = 1,000 × 1.00 = S/ 1,000
─────────────────────────────────
Total a distribuir = 5,000 + 500 + 1,000 = S/ 6,500
Cada dept paga: (participation × 6,500) + (su consumo × 1.00)
```

---

## 5. Archivos modificados

| Archivo | Tipo |
|---------|------|
| `database/migrations/2026_08_28_000001_add_is_common_to_water_readings_table.php` | Nuevo |
| `database/migrations/2026_08_28_000002_add_common_water_consumption_to_monthly_bills_table.php` | Nuevo |
| `app/Models/WaterReading.php` | Editado |
| `app/Models/MonthlyBills.php` | Editado |
| `app/Http/Controllers/Api/WaterReadingController.php` | Editado |
| `app/Http/Controllers/Api/MonthlyBillsController.php` | Editado |
| `app/Services/BillInvoiceService.php` | Editado |
| `resources/views/bills/invoice.blade.php` | Editado |
| `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue` | Editado |
| `frontend/src/resources/view/admin/WaterReadings/waterReadingForm.vue` | Editado |
| `frontend/src/resources/view/admin/WaterReadings/waterReadingEditForm.vue` | Editado |
| `frontend/src/resources/view/admin/WaterReadings/waterReadingsList.vue` | Editado |
| `frontend/src/resources/view/admin/WaterReadings/waterReadingDetails.vue` | Editado |
