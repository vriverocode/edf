# Instrucciones — Reservas (#6, #7, #8)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/reservesPage.vue`
- `frontend/src/resources/view/admin/Pays/validatePay.vue`
- `frontend/src/resources/view/admin/Pays/payMenu.vue`
- `frontend/src/resources/services/store/reserve.store.js`
- `frontend/src/resources/services/store/pay.store.js`
- `frontend/src/resources/routes/index.js`

---

### #6 — No hay flujo diferenciado para reservas de pago

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
El listado de reservas (`reservesPage.vue`) mezcla todas las reservas (gratis + de pago). Las reservas de áreas de pago requieren que el admin concilie el pago, pero no hay una vista especial para gestionarlas.

**Causa raíz:**
`reservesPage.vue` llama `reserveStore.getReservesByUser(filter)` que trae todas sin importar el tipo de área. El filtro `amount_type` existe en el filter pero no se usa activamente ni hay una vista dedicada.

**Solución:**

**Paso 1:** En `reservesPage.vue`, agregar un filtro rápido "Tipo de pago" con opciones: "Todas", "Gratis", "De pago", "Pendiente de aprobación".

**Paso 2:** Agregar un botón/sección "Pagos pendientes de validar" al inicio de la página, que muestre un contador de cuántas reservas de pago tienen `pay.status == 1` (pendiente de aprobación).

**Paso 3:** En cada tarjeta de reserva de pago, mostrar indicador visual del estado del pago:
   - Color verde: pagado y validado (`pay.status == 2`)
   - Color amarillo: pendiente de aprobación (`pay.status == 1`)
   - Color rojo: no pagado (`!booking.pay`)

**Paso 4:** Agregar botón "Validar pago" en cada reserva de pago que redirija a `/admin/pay/validate/:id` (esta ruta ya existe).

**Archivos a modificar:**
- `frontend/src/resources/view/admin/reservesPage.vue` — filtros, indicador de pago, contador pendientes
- `frontend/src/resources/services/store/reserve.store.js` — modificar `getReservesByUser` para aceptar `amount_type`

**Validación:**
1. Ver reservas con filtro "De pago" y ver solo las relevantes
2. Ver que las reservas pendientes de aprobación tienen indicador visible
3. Poder validar un pago desde el botón en la tarjeta de reserva

---

### #7 — No existe flujo de devolución tras cancelación con pago aceptado

**Prioridad:** 🔴 Alta
**Tipo:** Regla de negocio

**Problema:**
Un propietario paga una reserva de pago, el admin acepta el pago, luego el propietario cancela. El sistema no marca la reserva como "pendiente de devolución".

**Causa raíz:**
Cuando se cancela una reserva (`reserve.store.js` → `cancelReserve(id, motive)` → `POST /api/bookings/cancel/{id}`), el backend no verifica si ya hubo un pago validado. No existe concepto de "devolución pendiente" en el frontend ni en el store.

**Solución:**

**Paso 1:** Agregar un estado nuevo en el frontend para visualizar reservas canceladas con pago aceptado:
   - En `reservesPage.vue`, agregar filtro "Pendiente de devolución"
   - Mostrar badge/mensaje "💰 Pendiente de devolver S/. X" en la tarjeta

**Paso 2:** Modificar `reserve.store.js` — acción `cancelReserve` para que además de cancelar, determine si hay pago asociado:
   - Si la reserva tiene `pay` y `pay.status == 2`, marcar localmente como "devolución pendiente"
   - Mostrar notificación al admin: "Esta reserva tiene un pago validado. Gestiona la devolución en la sección Pagos."

**Paso 3:** En `validatePay.vue`, agregar una sección "Devoluciones pendientes":
   - Listado de reservas canceladas con pago aceptado
   - Botón "Registrar devolución" que crea un registro de egreso
   - Seleccionar cuenta financiera para la devolución

**Paso 4:** Coordinar con backend para que el endpoint de cancelación devuelva información del pago asociado y que exista un endpoint para registrar devoluciones.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/reservesPage.vue` — filtro "Pendiente devolución", badge
- `frontend/src/resources/view/admin/Pays/validatePay.vue` — sección de devoluciones
- `frontend/src/resources/services/store/reserve.store.js` — lógica post-cancelación

**Validación:**
1. Crear reserva de pago como propietario, pagar, admin validar
2. Cancelar la reserva (propietario o admin)
3. Verificar que aparece como "Pendiente de devolución"
4. Registrar devolución y verificar que desaparece de la lista

---

### #8 — Mismas fallas que en la vista del worker

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
El listado de reservas admin (`reservesPage.vue`) comparte estos problemas:
1. No muestra el número de departamento
2. Incluye reservas canceladas sin marcarlas claramente
3. Carga **todas las reservas** sin paginación

**Causa raíz:**
`reservesPage.vue` línea 34: `reserveStore.getReservesByUser(filter.value)` — no envía `page` ni `per_page`. El backend devuelve todo.
El template no extrae `booking.department?.number`.

**Solución:**

**Paso 1:** Agregar paginación en `reservesPage.vue`:
   - Variables: `page`, `lastPage`, `perPage`
   - Modificar `getReserves` para enviar `page` y `per_page`
   - Agregar `<q-pagination>` en el template

**Paso 2:** Modificar `reserveStore.getReservesByUser(filters)` para incluir `page` y `per_page` en el query.

**Paso 3:** En el template, mostrar el departamento de cada reserva:
   - `booking.department?.number || booking.apartment_number || booking.user?.units?.[0]?.number || '—'`

**Paso 4:** Las canceladas deben tener un estilo visual diferente (ej: tachado, gris, badge "Cancelada") en vez de mezclarse.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/reservesPage.vue` — paginación, mostrar depto, estilo canceladas
- `frontend/src/resources/services/store/reserve.store.js` — modificar `getReservesByUser` para paginación

**Validación:**
1. Cargar reservas: verificar que aparecen paginadas
2. Verificar que se ve el número de departamento
3. Verificar que las canceladas se ven diferentes
4. Cambiar de página y verificar que funciona

---

**Siguiente archivo:** `03-noticias-reportes.md` — Hallazgos #9, #10
