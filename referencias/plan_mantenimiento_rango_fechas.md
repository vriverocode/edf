# Plan: Mantenimiento por rango de fechas (bloqueo horario vs día completo)

**Fecha:** 2026-08-04
**Estado:** Pendiente de decidir un punto antes de implementar (ver "Pregunta pendiente" al final).

## Contexto

- El formulario actual (`createMaintenance.vue`) permite elegir duración en horas/días/semanas, pero solo envía UNA fecha + horario al backend. La duración de "3 días" queda solo como texto descriptivo.
- El modelo `Maintenance` guarda `date`, `time_from`, `time_to` por fila. La columna `time_from`/`time_to` **ya es nullable** (migración `2026_06_10_184920_create_maintenances_table.php`, líneas 27-28).
- Backend ya bloquea solo horas en la disponibilidad: `getAvaibleBookingByDay` (`BookingController.php:434-450`) marca "Ocupado" solo los slots superpuestos; `hasAvailableSlots` (`:773-782`) valida por rango horario; `MaintenanceController::store` solo cancela reservas solapadas.

## Problemas detectados

| Componente | Comportamiento actual |
|---|---|
| Backend `getAvaibleBookingByDay` (440-441) | `Carbon::parse(null)` → error si se guarda mantenimiento sin horas |
| Backend `hasAvailableSlots` (773-778) | SQL `NULL < $timeTo` no matchea → día completo NO bloquearía |
| Frontend `daysAvailableForBook` (`createReserve.vue:324-326`) | Bloquea el día completo ante cualquier mantenimiento (aunque sea por horas) |
| Frontend `fetchMaintenanceDates` (`createReserve.vue:300-310`) | Guarda solo fechas, descarta horas |
| Backend `MaintenanceController::store` | Solo crea 1 registro con 1 fecha |

## Decisión del usuario

- La fecha debe ser por **rango** (fecha inicio → fecha fin).
- Si el mantenimiento dura **más de 1 día** → las horas NO son obligatorias (área bloqueada todo el día).
- Si el mantenimiento es de **1 día** → las horas SÍ son obligatorias (se bloquean solo las horas).

## Plan propuesto

### Backend

1. **`MaintenanceController::store`** — aceptar `date_to` opcional:
   - Validación: `date_to` opcional con `after_or_equal:date`; **horas obligatorias solo si es 1 día** (sin `date_to` o igual a `date`).
   - Loop por día: crear N filas; multi-día con `time_from: null, time_to: null`.
   - Cancelar reservas: 1 día → query de solapamiento actual; multi-día → `whereBetween('date', [inicio, fin])`.
   - Notice con el rango de fechas en el texto.

2. **`BookingController::getAvaibleBookingByDay`** (líneas 440-441) — guard contra `Carbon::parse(null)`: si el mantenimiento no tiene horas → marcar **todos** los intervalos del día como "Ocupado".

3. **`BookingController::hasAvailableSlots`** (líneas 773-778) — añadir `->whereNull('time_from')` al query para que el día completo bloquee.

### Frontend

4. **`createMaintenance.vue`** — `date` pasa a rango (`date` + `date_to`):
   - Dos pickers (inicio/fin) con `dateOptions` existente.
   - Horas: visibles y obligatorias **solo si 1 día**; ocultas con nota "El área se bloqueará todo el día" si multi-día.
   - Validación en `submit()`: horas requeridas solo single-day; `date_to >= date`.
   - Payload: `date`, `date_to`, y `time_from`/`time_to` solo cuando aplique; resumen ajustado.

5. **`createReserve.vue`** (cliente):
   - `fetchMaintenanceDates` (300-310): guardar objetos `{date, time_from, time_to}` en vez de solo fechas.
   - `daysAvailableForBook` (324-326): bloquear la fecha **solo si** hay mantenimiento día-completo (horas null); si tiene horas → permitir la fecha (los slots ya salen "Ocupado" vía backend).

6. **`bookingsList.vue`** banner (línea 193) — mostrar "Todo el día" cuando horas null (hoy imprimiría "null-null").

7. **`MaintenanceList.vue`** (línea 68) — ya maneja null como 00:00-23:59; opcional mejorar a "Todo el día".

### Verificación

- `php -l`, Pint, build frontend.
- Manual:
  1. Mantenimiento de 1 día 11:00-15:00 → slots posteriores quedan libres.
  2. Mantenimiento de 3 días sin horas → días bloqueados completos.

## Pregunta pendiente

**¿N filas por día (sin campo nuevo) o campo `date_to` (1 sola entrada con rango)?**

- **Opción A — N filas por día (recomendada):** sin migración. El backend expande el rango en 1 fila por día; multi-día con horas null. Tradeoff: un mantenimiento de 3 días aparece como 3 entradas en `MaintenanceList.vue`.
- **Opción B — Campo `date_to`:** 1 sola entrada con el rango visible en la lista. Requiere migración y tocar todas las queries por fecha (`getByArea`, `getAvaibleBookingByDay`, `hasAvailableSlots`).

## Archivos relevantes

- `app/Http/Controllers/Api/MaintenanceController.php` — `store()`, `validateFieldsFromInput()`, `getByArea()`.
- `app/Http/Controllers/Api/BookingController.php` — `getAvaibleBookingByDay` (354, solapamiento 434-450), `hasAvailableSlots` (764, 773-782).
- `frontend/src/resources/view/admin/ComunAreas/createMaintenance.vue` — formulario (rango de fechas, horas condicionales).
- `frontend/src/resources/view/client/Reserves/createReserve.vue` — `fetchMaintenanceDates` (300), `daysAvailableForBook` (312).
- `frontend/src/resources/view/admin/ComunAreas/bookingsList.vue` — banner mantenimiento (185-197).
- `frontend/src/resources/view/admin/Maintenance/MaintenanceList.vue` — lista admin (línea 68 ya maneja null).
- `database/migrations/2026_06_10_184920_create_maintenances_table.php` — `time_from`/`time_to` nullable.
- `app/Models/Maintenance.php`.
