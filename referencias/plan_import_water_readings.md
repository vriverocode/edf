# Plan: Importación de Lecturas de Agua (WaterReadings)

## Archivo fuente
`./referencias/lectura_de_agua.xlsx`

## Fecha de corte
Miércoles 22-07-2026 (asamblea). Meses: **ENERO a JULIO 2026**.

## Estructura del Excel

| Columna | Contenido |
|---------|-----------|
| DPTO | Número de departamento (101, 102... 1712) |
| PROPIETARIOS(S) | Nombre del propietario |
| ENERO | Lectura acumulada mes 1 |
| FEBRERO | Lectura acumulada mes 2 |
| MARZO | Lectura acumulada mes 3 |
| ABRIL | Lectura acumulada mes 4 |
| MAYO | Lectura acumulada mes 5 |
| JUNIO | Lectura acumulada mes 6 |
| JULIO | Lectura acumulada mes 7 |

## Datos existentes en DB

| Ítem | Cantidad |
|------|----------|
| Departamentos tipo `dpt-*` en DB | 197 |
| Departamentos del Excel NO encontrados | 1 (702 - sin data) |
| WaterReadings actuales | 3 |
| Quotas sin `water_reading_id` (meses 1-7/2026) | ~554 |
| Precio agua en MonthlyBills | Solo Junio: 0.55/m³ |

---

## Comando a crear

**Archivo:** `app/Console/Commands/ImportWaterReadings.php`

**Firma:**
```bash
php artisan import:water-readings
```

---

## Fase A — Crear WaterReadings

Para cada departamento × cada mes (194 deptos × 7 meses = **1.358 registros**):

| Campo | Valor |
|-------|-------|
| `departament_id` | `Departament::where('number', "dpt-{n}")->id` |
| `month` | 1 (ENERO) ... 7 (JULIO) |
| `year` | 2026 |
| `current_reading` | Valor del Excel |
| `previous_reading` | **0 para ENERO**, lectura del mes anterior para el resto |
| `is_initial` | **1 para ENERO**, 0 para el resto |
| `m3_price` | **0.55** (fijo para todos los meses) |
| `amount` | **0** (no se calcula) |

### Reglas
- `firstOrCreate` por `(departament_id, month, year)` — evita duplicados
- Depto no encontrado: se salta y reporta
- Leer Excel con `data_only=True` para resolver fórmulas

---

## Fase B — Vincular a Quotas existentes

Después de crear cada WaterReading:

```sql
UPDATE quotas
SET water_reading_id = {wr->id}, water_amount = 0
WHERE departament_id = {wr->departament_id}
  AND month = {wr->month}
  AND YEAR(due_date) = 2026
  AND water_reading_id IS NULL
```

- Si la Quota no existe → se salta (la WaterReading queda disponible para MonthlyQuota futuro)
- Si la Quota ya tenía `water_reading_id` → no se reemplaza

---

## Edge cases

| Caso | Manejo |
|------|--------|
| Depto 702 no existe en DB | Saltar + reportar en tabla |
| Fórmulas en Excel (depto 1201) | `data_only=True` las resuelve (`=567+199` → `766`) |
| Valores decimales (180.5, 281.174) | `decimal(10,2)` los almacena con 2 decimales |
| WaterReading ya existía | `firstOrCreate` lo ignora |
| Quota sin `water_reading_id` aún | Solo se vincula si existe |
| ENERO sin lectura previa | `previous_reading = 0`, `is_initial = 1` |

---

## Reporte de salida esperado

```
+--------------------------------------------+-------+
| Métrica                                   | Valor |
+--------------------------------------------+-------+
| WaterReadings creados                     | 1358  |
| Departamentos no encontrados (saltados)   | 1     |
| Cuotas vinculadas (water_reading_id set)  | ~554  |
| Cuotas sin vínculo (no existen aún)       | X     |
+--------------------------------------------+-------+
```
