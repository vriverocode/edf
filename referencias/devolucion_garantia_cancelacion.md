# Devolución por garantía vs cancelación — Campo `kind` + automatizaciones de reservas

**Fecha:** 2026-08-05
**Estado:** Implementado y probado (backend + frontend). Pendiente de desplegar en el servidor.

## Resumen de lo hecho hoy

1. **Campo `kind` en reservas y devoluciones** para distinguir el origen de la devolución.
2. **Devolución por garantía** devuelve **solo `warranty_price`** del área (leído del área al momento de devolver).
3. **Job automático `AutoCompleteBookings`** cada 30 min: completa reservas en estado Exitoso cuyo horario ya inició/finalizó.
4. **`BookingPendingPaymentReminders`**: cancelar reservas sin pagar (status 1) cuando falten **≤72 h** (antes 24 h) y fix de parseo de fecha.
5. **Blindaje del vaucher**: un tipo-2 con pago activo ya registrado → `409`; `uploadVaucher` no sobrescribe.
6. **Cancelación generalizada**: cualquier reserva pagada (`amount > 0`) cancelada pasa a pendiente de devolución.

---

## ¿Qué significa `kind`?

- `warranty` → la reserva se **completó** y hay que devolver la garantía del área.
- `cancellation` → la reserva fue **cancelada** (paga) y hay que devolver el pago.

Se guarda en dos tablas: `bookings.kind` (origen a nivel reserva) y `table_refunds.kind` (origen de cada devolución registrada).

### Flujo por garantía (completar reserva)

1. `BookingController::completeBooking` o el job `AutoCompleteBookings` detecta `comunArea->warranty_price > 0` y `pay.status == 2`.
2. La reserva pasa a **6 (Pend. devolución)** con `kind = 'warranty'`.
3. El admin devuelve en `validatePay.vue` o `reservesPage.vue`:
   - El monto se **fuerza a `warranty_price`** del área (no el total).
   - La reserva pasa a **4 (Completada)**.
   - El pago pasa a 5 (reembolsado) o 4 (parcial).
   - `reason = "Devolución de garantía por reserva completada"`.

### Backend por cancelación

1. `cancelBooking` detecta `amount > 0` y status distinto de Completada.
2. La reserva pasa a `6 (Pendiente de devolución)` con `kind = 'cancellation'`; el pago a 6.
3. Al devolver: se usa el monto enviado, reason `"Devolución por cancelación de reserva"`, la reserva pasa a **0 (Cancelada)**.

---

## Archivos modificados / creados

**Backend**
- `database/migrations/2026_08_05_213309_add_kind_to_bookings_and_refunds.php` (nuevo, idempotente: añade `kind` a `bookings` y `table_refunds`; también `type` en `table_refunds` si faltaba)
- `app/Http/Controllers/Api/PayController.php` — `refund()` (monto/origen por `kind`; reserva → 4 si garantía, 0 si cancelación)
- `app/Http/Controllers/Api/BookingController.php` — `cancelBooking` (kind), `completeBooking` (kind), `completeReserveNotification` (ahora `public static`)
- `app/Console/Commands/AutoCompleteBookings.php` (nuevo) — completa reservas Exitoso pasadas de hora
- `app/Console/Commands/BookingPendingPaymentReminders.php` — umbral 72 h + fix `->toDateString()`
- `app/Models/Booking.php` — `kind` en fillable, accessor `kind_label`
- `app/Models/Refund.php` — **`$table = 'table_refunds'`** (bug: consultaba `refunds` inexistente), `kind` en fillable
- `routes/console.php` — registra jobs cada 30 min

**Frontend**
- `frontend/src/resources/view/admin/Pays/validatePay.vue` — badge del origen, monto por garantía, diálogo con origen
- `frontend/src/resources/view/client/reserves/view/'.$booking->idPage.vue` — badge "Garantía/Cancelación pendiente de devolver S/. X", tooltip con origen
- Build a `frontend/dist/` (gitignored) — renovar antes de Capacitor

---

## Estados involucrados

**Booking**: 0 Cancelada · 1 Pago pend. · 2 Pend. aprob. · 3 Exitoso · 4 Completada · 5 Pend. reembolso (legacy) · **6 Pend. devolución**

**Pay**: 0 Cancelado · 1 Pend. aprob. · 2 Exitoso · 3 Rechazado · 4 Reemb.parcial · 5 Reembolsado · **6 Pendiente por devolución**

---

## Manual de despliegue (SERVIDOR)

> El usuario sube los archivos a mano a `SERVIDOR/` (no va en commits). La BD se despliega manualmente.

1. **Subir archivos modificados** de hoy (lista de arriba) y borrar/ sustituir los de `SERVIDOR/`.
2. **Correr la migración** en el servidor:
   ```bash
   php artisan migrate --force
   ```
3. **Reiniciar el scheduler (cron) y el worker de cola:**
   ```bash
   # cron `schedule:run` ya configurado; debe apuntar a:
   # * * * * * cd /ruta/app && php artisan schedule:run >> /dev/null 2>&1
   php artisan queue:restart   # workers que escuchan las notificaciones
   ```
4. **Verificar jobs programados:**
   ```bash
   php artisan schedule:list
   # app:auto-complete-bookings       → cada 30 min
   # booking-pending-pay-reminders        → cada 30 min
   ```
4b. **Probar el job de completado manualmente:**
   ```bash
   php artisan app:auto-complete-bookings
   ```
5. Reconstruir el frontend y Capacitor sync (si se tocan vistas admin):
   ```bash
   cd frontend && npm run build && npx cap sync
   ```
6. Sync de credenciales Pusher/ `.env si cambiaron.

## Notas importantes

- La devolución por garantía lee el `warranty_price y monto del área ** al momento de devolver** (si el precio cambia entre medio cambia el monto).
- El devol por garantía **no cancela** la reserva (queda Completada). La cancelación sí la cancela.
- `RefundService.php` estaba roto por la tabla `refunds` inexistente, corregido con `$table = 'table_refunds'`.
- Las tablas de BD local y de migraciones pueden estar desalineadas (ej. `bookings.kind` ya existía sin migración); la migración es idempotente a propósito.

## Verificación hecha

- `php -l` en todos los archivos PHP tocados. OK.
- `./vendor/bin/pint` en los archivos de hoy. OK (`app/Models/Provider.php` preexistente pendiente).
- Build frontend `npm run build`. OK.
- Simulación real en BD (datos temporales luego eliminados):
  - Garantía: booking 6 + `kind=warranty` + pay 2 → refund pide 5000 → devuelve 100 (warranty_price), reserva → 4, pay → 5, `kind=warranty`.
  - Cancelación: booking 6 + `kind=cancellation` + pay 6 → refund 45 → reserva → 0, pay → 4, `kind=cancellation`.