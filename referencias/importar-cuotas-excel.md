# Importar cuotas desde Excel

> Archivo origen: `referencias/LISTA DE PROPIETARIOS AL DIA.xlsx`
> Fecha del análisis: 2026-07-28

## 📋 Resumen del Excel

- **128 propietarios** listados
- Columnas mensuales: **Periodo 2025**, **2026-01**, **2026-02**, **2026-03**, **2026-04**, **2026-05**
- Última columna: **DEUDA HASTA MAYO 2026** (todos en 0,00 — verde condicional)
- **Celdas amarillas** = cuotas **pendientes por pagar** (status=1)
- **Celdas blancas** = sin deuda / sin datos
- **128 filas**, de las cuales **127** tienen al menos una celda amarilla

## 🎨 Codificación de colores

| Color | Significado |
|-------|-------------|
| `FFFFFF00` (amarillo) | Cuota pendiente por pagar → crear `Quota` con `status=1` |
| `FFFFFFFF` (blanco) | Sin cuota / ya pagado |
| `FF8AEEC1` (verde) | Deuda 0 — condicional, solo en columna DEUDA |
| `FFF7CAAC` (durazno) | Casos especiales en Periodo 2025 (2 filas) |

## 📊 Distribución de datos

| Columna | Cabecera | Valores | Amarillos |
|---------|----------|---------|-----------|
| D | Periodo 2025 (DELHEL y ALGORITMO) | 9 | 7 |
| E | 2026-01 | 16 | 15 |
| F | 2026-02 | 24 | 22 |
| G | 2026-03 | 35 | 33 |
| H | 2026-04 | 79 | 77 |
| I | 2026-05 | 127 | 127 |
| J | DEUDA HASTA MAYO 2026 | 128 | 0 |

## 🔗 Mapeo propiedades → DB

| Excel | DB | Match |
|-------|----|-------|
| `DEPA-NNN` | `dpt-NNN` (type=1) | ✅ Casi todos |
| `ESTA-NNN` | `EST-NNN` (type=2) | ✅ Depende si existe |
| `DEPO-NNN` | `DEPO-NNN` (type=3) | ❌ No existe en DB |

### Crear departamentos faltantes

Antes de importar cuotas hay que crear:

1. **ESTA-140** → `Departament { number: "EST-140", type: 2 }` (estacionamiento)
2. **DEPO-160** → `Departament { number: "DEPO-160", type: 3 }` (depósito)

## 🏗️ Implementación

### Archivo a crear

**`app/Console/Commands/ImportOwnerQuotasFromExcel.php`**

```php
<?php

namespace App\Console\Commands;

use App\Models\Departament;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportOwnerQuotasFromExcel extends Command
{
    protected $signature = 'import:owner-quotas-from-excel';

    protected $description = 'Importa cuotas desde LISTA DE PROPIETARIOS AL DIA.xlsx';

    public function handle()
    {
        // 1. Crear departamentos faltantes
        $this->createMissingDepartments();

        // 2. Leer Excel y crear cuotas
        $file = storage_path('app/referencias/LISTA DE PROPIETARIOS AL DIA.xlsx');
        // ... o mantener en referencias/ y pasar ruta como argumento
    }
}
```

### Lógica de importación

#### Paso 1 — Leer Excel
Usar PhpSpreadsheet (viene con `maatwebsite/excel` ya instalado).

```php
$spreadsheet = IOFactory::load($filePath);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();
```

#### Paso 2 — Normalizar código de propiedad
```php
function normalizeProperty(string $code): ?string
{
    $code = strtoupper(trim($code));
    if (str_starts_with($code, 'DEPA-')) {
        return 'dpt-' . substr($code, 5);
    }
    if (str_starts_with($code, 'ESTA-')) {
        return 'EST-' . substr($code, 5);
    }
    if (str_starts_with($code, 'DEPO-')) {
        return $code; // DEPO-160
    }
    return null;
}
```

#### Paso 3 — Detectar celda amarilla
```php
function isYellow($cell): bool
{
    $fill = $cell->getFill();
    if (!$fill) return false;
    $color = $fill->getFgColor();
    return $color && $color->getRGB() === 'FFFF00'; // o 'FFFFFF00'
}
```

#### Paso 4 — Crear Quota
```php
Quota::create([
    'departament_id' => $departament->id,
    'month' => $month,
    'due_date' => Carbon::create($year, $month, 10)->format('Y-m-d'),
    'maintenance_amount' => $amount,
    'water_amount' => 0,
    'amount' => $amount,
    'type' => 1,
    'status' => 1, // Pago pendiente
    'description' => $description,
    'number' => 'IMP-' . $departament->number . '-' . $month,
]);
```

#### Paso 5 — Recorrer filas

| Col Excel | Índice | Mes | Año | Descripción |
|-----------|--------|-----|-----|-------------|
| D | 3 | 12 | 2025 | "Periodo 2025 (DELHEL y ALGORITMO)" |
| E | 4 | 1 | 2026 | "Cuota Enero - 2026" |
| F | 5 | 2 | 2026 | "Cuota Febrero - 2026" |
| G | 6 | 3 | 2026 | "Cuota Marzo - 2026" |
| H | 7 | 4 | 2026 | "Cuota Abril - 2026" |
| I | 8 | 5 | 2026 | "Cuota Mayo - 2026" |

### Resumen final
```php
$this->table(['Concepto', 'Cantidad'], [
    ['Departamentos creados', $deptsCreated],
    ['Cuotas creadas', $quotasCreated],
    ['Propiedades sin match', $unmatched],
    ['Filas procesadas', $rowsProcessed],
]);
```

## 📝 Notas adicionales

1. **Dependencias**: `maatwebsite/excel` ya está en `composer.json` → incluye PhpSpreadsheet. No requiere instalación extra.
2. **Ruta del archivo**: El Excel está en `referencias/`. Se puede copiar a `storage/app/` o pasar la ruta como argumento del comando.
3. **Duplicados**: El comando NO debe crear cuotas duplicadas. Validar si ya existe `Quota` con mismo `departament_id`, `month` y `year`.
4. **Notificaciones**: No enviar notificaciones al crear cuotas históricas (a diferencia de `MonthlyQuota.php` que sí notifica).
5. **Validación de montos**: Algunas celdas tienen valores negativos (ej: `-7,07`, `-0,02`). Decidir si crear cuota con monto negativo o ignorarlas.
6. **Formato numérico**: Los montos vienen con coma decimal (`378,38`). Convertir a punto antes de guardar.

## ✅ Pasos para ejecutar

```bash
# 1. Copiar el Excel a storage (opcional)
cp referencias/LISTA\ DE\ PROPIETARIOS\ AL\ DIA.xlsx storage/app/

# 2. Ejecutar la importación
php artisan import:owner-quotas-from-excel
```
