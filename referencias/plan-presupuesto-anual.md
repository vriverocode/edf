# Plan: Sistema de Presupuesto Anual

**Fecha:** 2026-09-15
**Estado:** Pendiente de implementación

---

## Objetivo

Crear un sistema de presupuestos anuales que permita:
1. Definir el presupuesto anual con sus gastos mensuales
2. Marcar gastos recurrentes (se repiten todos los meses)
3. Generar automáticamente los `monthly_bills` y `expenses` de cada mes

---

## 1. Nueva tabla `annual_budgets`

```php
Schema::create('annual_budgets', function (Blueprint $table) {
    $table->id();
    $table->integer('year');
    $table->string('name');                          // "Presupuesto 2026"
    $table->decimal('total_monthly_budget', 15, 2);  // Presupuesto mensual total
    $table->integer('status')->default(1);            // 1=Borrador, 2=Activo, 3=Cerrado
    $table->timestamps();
});
```

---

## 2. Modificar tabla `expenses`

**Migración:** `add_template_fields_to_expenses_table`

```php
Schema::table('expenses', function (Blueprint $table) {
    // Hacer provider_id nullable (era required)
    $table->foreignId('provider_id')->nullable()->change();

    // Campos nuevos para templates de presupuesto
    $table->boolean('is_template')->default(false);    // true = item del presupuesto
    $table->foreignId('annual_budget_id')->nullable()->constrained('annual_budgets');
    $table->integer('sort_order')->default(0);          // Orden de presentación
});
```

**Relación con AnnualBudget:**
```php
// En Expense.php
public function annualBudget()
{
    return $this->belongsTo(AnnualBudget::class);
}
```

---

## 3. Modelo `AnnualBudget`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualBudget extends Model
{
    protected $fillable = ['year', 'name', 'total_monthly_budget', 'status'];

    const STATUS_BORRADOR = 1;
    const STATUS_ACTIVO = 2;
    const STATUS_CERRADO = 3;

    public function templates()
    {
        return $this->hasMany(Expense::class, 'annual_budget_id')
            ->where('is_template', true);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVO);
    }
}
```

---

## 4. Job `GenerateMonthlyBudgetJob`

```php
// Flujo:
// 1. Buscar AnnualBudget activo para el año
// 2. Crear MonthlyBill si no existe para ese mes/año
// 3. Para cada Expense template (is_template=true):
//    - Copiar a nuevo Expense con:
//      - monthly_bill_id = MonthlyBill.id
//      - is_template = false
//      - amount = amount del template
//      - issue_date = primer día del mes
//      - due_date = último día del mes
//      - status = 1 (Pendiente)
//    - Si ya existe similar para ese mes → skip (idempotente)
```

---

## 5. Comando `import:presupuesto-anual`

```php
// Firma: import:presupuesto-anual {file?}
// Default: referencias/Presupuesto_2026.xlsx
//
// Proceso:
// 1. Leer hoja año_2026 del Excel
// 2. Crear AnnualBudget (year=2026, name="Presupuesto 2026", status=2)
// 3. Para cada fila de gastos:
//    - Crear Expense template con:
//      - annual_budget_id = ID del presupuesto
//      - is_template = true
//      - description = nombre del gasto
//      - amount = monto mensual
//      - expense_type = 1 (Ordinario/Recurrente)
//      - service_category_id = buscar o crear categoría
//      - sort_order = orden de fila
// 4. Mostrar resumen
```

---

## 6. Archivos a crear/modificar

| Archivo | Acción |
|---------|--------|
| `database/migrations/2026_09_15_000001_create_annual_budgets_table.php` | Crear |
| `database/migrations/2026_09_15_000002_add_template_fields_to_expenses_table.php` | Crear |
| `app/Models/AnnualBudget.php` | Crear |
| `app/Models/Expense.php` | Modificar |
| `app/Jobs/GenerateMonthlyBudgetJob.php` | Crear |
| `app/Console/Commands/ImportPresupuestoAnual.php` | Crear |

---

## 7. Ejecución

```sh
# 1. Crear migraciones
php artisan migrate

# 2. Importar presupuesto anual desde Excel
php artisan import:presupuesto-anual

# 3. Generar budget para un mes específico
php artisan bus:dispatch GenerateMonthlyBudgetJob --month=1 --year=2026
```

---

## 8. Estructura de datos resultante

```
AnnualBudget (2026)
├── Expense template: "Agua Sedapal" - S/5000/mes (is_template=true)
├── Expense template: "Electricidad" - S/15000/mes (is_template=true)
├── Expense template: "Ascensores" - S/1000/mes (is_template=true)
└── Expense template: "Gastos legales" - S/400/mes (is_template=true)

MonthlyBill (mes=1, year=2026) [generado por Job]
├── Expense: "Agua Sedapal" S/5000 (is_template=false, monthly_bill_id=X)
├── Expense: "Electricidad" S/15000 (is_template=false, monthly_bill_id=X)
└── Expense: "Ascensores" S/1000 (is_template=false, monthly_bill_id=X)
```
