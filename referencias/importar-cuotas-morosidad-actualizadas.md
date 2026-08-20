# Importar cuotas de morosidad actualizadas (cuotasActualizadas.xlsm)

> Archivo origen: `referencias/cuotasActualizadas.xlsm`
> Hoja: **REPORTE DE MOROSIDAD**
> Fecha del análisis: 2026-08-19

## 📋 Resumen del Excel

- **170 filas** de propietarios/departamentos (filas 5–174, fila 175 = TOTAL GENERAL ignorada)
- Columnas mensuales:

| Col | Periodo | Mes | Año |
|-----|---------|-----|-----|
| C | PERIODO 2025 (acumulado) | 0 | 2025 |
| D | ENERO 2026 | 1 | 2026 |
| E | FEBRERO 2026 | 2 | 2026 |
| F | MARZO 2026 | 3 | 2026 |
| G | ABRIL 2026 | 4 | 2026 |
| H | MAYO 2026 | 5 | 2026 |
| I | JUNIO 2026 | 6 | 2026 |
| J | JULIO 2026 | 7 | 2026 |
| K | TOTAL NETO | — | — |

- **447 celdas** con monto (saldo neto pendiente de mantenimiento), **22 de ellas negativas** (saldos a favor/abonos)
- Título del reporte: `MANTENIMIENTO — SALDO NETO PENDIENTE` (solo mantenimiento, sin agua)

## 🗺️ Mapeo a la tabla `quotas`

| Excel | DB | Notas |
|-------|----|-------|
| Número (`101`, `1001`) | `dpt-101` (type=1) | Departamento |
| `ESTA-NNN` | `EST-NNN` (type=2) | Estacionamiento |
| `DEPO-NNN` | `DPO-NNN` (type=3) | Depósito |
| `ESTA-046\nESTA-062\nESTA-100` | 3 departamentos | Mismo propietario, se crea cuota en cada uno |
| `ESTA-S/ID`, `LAV-001` | ❌ Omitidas | Códigos no mapeables |

Reglas por celda con monto:

- `amount` = `maintenance_amount` = valor de la celda; `water_amount` = 0
- `due_date` = último día del mes (`2025-12-31` para Periodo 2025, `2026-06-30`, `2026-07-31`, …)
- `status`:
  - **monto ≥ 0 → 4 (Vencida)**
  - **monto < 0 → 3 (Pagada)** — sin crear registro en `pays`
- `type` = tipo del departamento; `description` = `Cuota {Periodo/Mes} - {depto}`; `number` = null
- Dedupe por `departament_id + month + year` (no crea duplicados)

## 🏗️ Implementación

**Archivo:** `app/Console/Commands/ImportCuotasActualizadasMorosidad.php`

```bash
php artisan import:cuotas-actualizadas-morosidad
```

- Lee el `.xlsm` directo con PhpSpreadsheet (`maatwebsite/excel` ya instalado), sin paso intermedio de Python
- Crea los departamentos faltantes (estacionamientos EST-*) y asigna `user_id` al propietario si se encuentra en `users` (helper `findUserByName`)
- No envía notificaciones (importación histórica)
- Reporte de omitidos: `storage/app/import-cuotas-actualizadas-morosidad.md`

## ✅ Resultado de la importación (2026-08-19)

| Métrica | Valor |
|---------|-------|
| Cuotas creadas | **447** (425 status=4, 22 status=3) |
| Departamentos creados | 11 (estacionamientos EST-*) |
| Propietarios encontrados | 153 |
| Propietarios NO encontrados | 15 |
| Filas omitidas | 9 (2 no mapeables + 7 sin user_id, estas últimas sí importaron cuotas) |

Verificaciones:

- `dpt-103`: 8 cuotas, suma 1864.01 (coincide con el Excel)
- `EST-046`, `EST-062`, `EST-100`: 2 cuotas c/u, suma 449 (228.16 + 220.84)
- Totales por mes coinciden con la fila TOTAL del Excel (Periodo 2025 = 16411.11, etc.)

## 📝 Notas

1. La base de datos se vació de las tablas `quotas`/`pays` antes de importar (departaments y users quedaron intactos).
2. Los 7 estacionamientos de "BERTOLOTTO INVESTMENTS S.A.C" se crearon sin `user_id` porque esa razón social no existe en `users`; las cuotas sí se importaron. Asociar dueño posteriormente si corresponde.
3. Montos negativos = saldos a favor (abonos), quedaron como cuotas `status=3` sin pago asociado.