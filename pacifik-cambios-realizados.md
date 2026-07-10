# Cambios realizados — Pacifik Bugs & Usabilidad

**Fecha:** 2026-07-09

---

## #1 — Restringir inquilino a 1 unidad

### Backend
- **Archivo:** `app/Http/Controllers/Api/UserController.php`
  - La validación existente (línea 115-122) ya impedía dos inquilinos en el mismo departamento. No se requirieron cambios adicionales.

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Familiar/createFamiliar.vue`
  - Se agregó `deptosWithInquilino` (Set reactivo)
  - Se agregó función `getInquilinosByUser()` que consulta residentes existentes y construye el Set de departamentos que ya tienen inquilino
  - Se filtraron los apartments por `a.type == 1` (solo departamentos, excluye estacionamientos/depósitos)
  - Se agregó validación en step 0: si tipo es "inquilino" y el depto ya tiene uno, muestra error y bloquea
  - Se agregó validación de fechas en step 2: `end_time` no puede ser anterior a `init_time`
  - Se agregó `getInquilinosByUser()` al `onMounted`

---

## #3 — Modal de filtros con badge en reserveList

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Reserves/reserveList.vue`
  - Se agregó estado `userFilters` con defaults: `hideCanceled: true`, `hidePast: true`, `date_from: hoy`
  - Se agregó `activeFilterCount` (computed) que cuenta filtros activos
  - Se agregó `filteredReserves` (computed) que aplica filtros sobre `reserves`
  - Se agregó `resetFilters()` para limpiar filtros
  - Se agregó botón de filtro (icono funnel) con badge flotante que muestra contador
  - Se agregó `q-dialog` modal con opciones:
    - Checkbox "Ocultar canceladas"
    - Checkbox "Solo próximas (>= hoy)"
    - Select de estado
    - Input "Hasta fecha"
    - Botones "Limpiar" y "Aplicar"
  - Template actualizado para usar `filteredReserves` en lugar de `reserves`
  - Empty state actualizado para diferenciar "Sin resultados" vs "No tienes reservas"

---

## #4/#22 — Timezone fechas (Perú)

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Reserves/viewReserve.vue`
  - Línea 152: `new Date(booking.date).toLocaleDateString('es-ES')` → `moment(booking.date).format('DD/MM/YYYY')`

- **Archivo:** `frontend/src/resources/view/client/Reserves/confirmReserve.vue`
  - Se agregó `import moment from 'moment'`
  - Línea 153: `new Date(booking.date).toLocaleDateString('es-ES')` → `moment(booking.date).format('DD/MM/YYYY')`

- **Archivo:** `frontend/src/resources/view/client/Visits/viewVisit.vue`
  - Línea 118: `new Date(visit.date).toLocaleDateString('es-ES')` → `moment(visit.date).format('DD/MM/YYYY')`

- **Archivo:** `frontend/src/resources/view/client/Payments/payFinish.vue`
  - Se agregó `import moment from 'moment'`
  - Línea 146: `new Date(pay.pay_date).toLocaleDateString('es-ES')` → `moment(pay.pay_date).format('DD/MM/YYYY')`

- **Archivo:** `frontend/src/resources/view/admin/Pays/validatePay.vue`
  - Se agregó `import moment from 'moment'`
  - Línea 315: `new Date(pay.pay_date).toLocaleDateString('es-PE')` → `moment(pay.pay_date).format('DD/MM/YYYY')`

- **Archivo:** `frontend/src/resources/view/admin/Expenses/expensesList.vue`
  - Se agregó `import moment from 'moment'`
  - Línea 67: `date.toLocaleDateString('es-PE')` → `moment(date).format('DD/MM/YYYY')`

---

## #5 — Mostrar departamento en reserva

### Backend
- **Archivo:** `app/Http/Controllers/Api/BookingController.php`
  - `getBookingsByUser()`: Se agregó `'departament'` al `with()`
  - `getBookingById()`: Se agregó `'departament'` al `with()`

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Reserves/reserveList.vue`
  - Se agregó `<div v-if="reserve.departament">Unidad #{{ reserve.departament.number }}</div>` bajo el título

- **Archivo:** `frontend/src/resources/view/client/Reserves/viewReserve.vue`
  - Se agregó bloque "Unidad: #{{ booking.departament?.number }}" en los detalles

- **Archivo:** `frontend/src/resources/view/client/Reserves/confirmReserve.vue`
  - Se agregó línea "Unidad" en la tarjeta de detalles

---

## #6 — Botón volver explícito paso 3

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Reserves/createReserve.vue`
  - Se agregó botón "Volver" (color grey, outline) junto al botón "Cambiar fecha" existente

---

## #12 — Validar fecha futura en incidencias

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Incidents/createIncident.vue`
  - Se agregó `:max="moment().format('YYYY-MM-DD')"` al input de tipo `date`

---

## #13 — Mostrar fecha de registro en listado de incidencias

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Incidents/incidentList.vue`
  - Se agregó línea "Reportado {{ moment(incident.created_at).format('DD MMM YYYY') }}" con ícono de reloj

---

## #14 — Mostrar hora en detalle de incidencias

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Incidents/viewIncident.vue`
  - Línea 90: `moment(incident.created_at).format('DD/MM/YYYY')` → `moment(incident.created_at).format('DD/MM/YYYY HH:mm')`
  - Se eliminó el bloque "Hora:" separado (ya se muestra junto con la fecha)

---

## #19 — Orden descendente en listado de residentes

### Backend
- **Archivo:** `app/Http/Controllers/Api/UserController.php`
  - `getResident()`: Se agregó `->orderBy('created_at', 'desc')` antes de `->get()`

---

## #20 — Ocultar botón editar visita

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Visits/visitsList.vue`
  - Se reemplazó el botón `eva-edit-2-outline` (con `@click="noDisponible()"`) por un comentario `<!-- Edit removed -->`

---

## #23 — Validar hora pasada al crear visita

### Frontend
- **Archivo:** `frontend/src/resources/view/client/Visits/createVisit.vue`
  - En `validateData()`: Se agregó validación que si la fecha seleccionada es hoy, verifica que la hora no sea anterior a la hora actual

---

## #24 — Placeholder "Próximamente" en finanzas

### Frontend
- **Archivo:** `frontend/src/resources/view/admin/balancesPage.vue`
  - Se agregó `import { Notify } from 'quasar'`
  - Se agregó flag `placeholder: true` a los items "Saldos pendientes" y "Gastos comunes"
  - Función `goTo()` modificada: si el item tiene `placeholder`, muestra notificación "Próximamente disponible"
  - Se agregó clase `opacity-50` a los items placeholder

---

## #25 — Reforzar indicador activo en footer

### Frontend
- **Archivo:** `frontend/src/resources/components/layout/navbarAdmin.vue`
  - `.q-tab__indicator`: `display: none` eliminado, se agregó `background: #02205d; height: 3px`
  - `.q-tab--active`: Se agregó `background: rgba(2, 32, 93, 0.06)` y `font-weight: 700`
