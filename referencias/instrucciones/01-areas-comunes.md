# Instrucciones — Áreas Comunes (#1, #2, #3, #4, #5)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/ComunAreas/comunAreasList.vue`
- `frontend/src/resources/view/admin/ComunAreas/bookingsList.vue`
- `frontend/src/resources/view/admin/ComunAreas/createComunArea.vue`
- `frontend/src/resources/view/admin/ComunAreas/updateComunArea.vue`
- `frontend/src/resources/view/admin/ComunAreas/createMaintenance.vue`
- `frontend/src/resources/view/admin/reservesPage.vue`
- `frontend/src/resources/services/store/reserve.store.js`
- `frontend/src/resources/services/store/comunArea.store.js`

---

### #1 — "Ver reservas históricas" no es un histórico

**Prioridad:** 🟠 Media
**Tipo:** Bug funcional

**Problema:**
El botón "Ver reservas históricas" en cada área común redirige a `bookingsList.vue`, pero esa vista:
1. Muestra reservas **futuras** (no históricas)
2. Incluye reservas canceladas
3. No muestra el número de departamento que hizo la reserva
4. No tiene filtros ni paginado
5. Carga todas sin paginar

**Causa raíz:**
- `comunAreasList.vue`: el botón llama `goTo('/admin/comun-area/bookings/' + comunArea.id + '/list')` sin especificar filtro de fecha
- `bookingsList.vue`: llama `reserveStore.getReservesByArea(route.params.id)` que trae **todas** las reservas del área sin filtro de estado ni paginación
- `reserve.store.js`: `getReservesByArea(area)` hace `GET /api/bookings/byArea/{area}` — el backend devuelve todo sin filtrar

**Solución:**

**Paso 1:** Modificar `bookingsList.vue` para agregar paginación:
- Agregar variable `page` y `lastPage`
- Modificar `getReservesByArea` para enviar `?page=&status=&date_from=&date_to=`
- Agregar filtros visuales: selector de mes/año, checkbox "Mostrar canceladas"
- Mostrar `booking.department.number` o `booking.user.units[0].number` en cada fila

**Paso 2:** Modificar `reserve.store.js` — acción `getReservesByArea(area, params)`:
```js
getReservesByArea(area, params = {}) {
  const query = filterQuery(params) // page, status, date_from, date_to
  return ApiService.get(`/api/bookings/byArea/${area}${query}`)
}
```

**Paso 3:** Modificar `bookingsList.vue` template:
- Cambiar título de "Reservas" a "Reservas del área" con selector de período
- Agregar paginación con `q-pagination`
- Filtro por defecto: solo reservas con `status != cancelled` (status 3 o la lógica de negocio)
- Mostrar datos del departamento: `booking.department?.number || booking.user?.units?.[0]?.number || '—'`
- Agregar estado de carga (`q-spinner`)

**Archivos a modificar:**
- `frontend/src/resources/view/admin/ComunAreas/bookingsList.vue` — agregar paginación, filtros, mostrar depto
- `frontend/src/resources/services/store/reserve.store.js` — modificar `getReservesByArea` para aceptar params

**Validación:**
1. Ir a Áreas comunes → hacer clic en "Ver reservas históricas" de un área
2. Verificar que solo muestra reservas pasadas (no futuras)
3. Verificar que no muestra canceladas (a menos que se active el filtro)
4. Verificar que se ve el número de departamento
5. Verificar que funciona la paginación

---

### #2 — Botón "Crear reserva" del admin permite reserva sin departamento

**Prioridad:** 🔴 Alta
**Tipo:** Bug funcional

**Problema:**
El admin puede crear una reserva desde el listado de áreas comunes. Como el admin no tiene un departamento asociado, la reserva queda "flotando" sin propietario/unidad.

**Causa raíz:**
`comunAreasList.vue` tiene un botón que probablemente redirige a un formulario de creación de reserva (`/client/reserves/form/add` o similar) que asume que el usuario autenticado tiene un departamento. Cuando el admin usa este flujo, no hay departamento que asociar.

**Solución:**

**Paso 1:** Identificar el botón en `comunAreasList.vue` — buscar en el template el botón/modal para "Crear reserva" (revisar líneas 80-130).

**Paso 2:** Modificar el flujo para que, antes de abrir el formulario de creación de reserva, el admin deba seleccionar:
   a. Un propietario/usuario del sistema (con buscador)
   b. O un departamento específico

**Paso 3:** Si el flujo es un modal, agregar un paso inicial de selección de propietario con `q-select` + `q-dialog` que cargue usuarios con rol propietario (llamando `userStore.getUsers({rol: 2})`).

**Paso 4:** Pasar el `user_id` seleccionado como parámetro al formulario de creación de reserva.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/ComunAreas/comunAreasList.vue` — agregar modal de selección de propietario antes de crear reserva
- Posiblemente `frontend/src/resources/view/client/Reserves/createReserve.vue` — aceptar `userId` como prop/query param

**Validación:**
1. Como admin, ir a Áreas comunes
2. Hacer clic en "Crear reserva"
3. Verificar que pide seleccionar un propietario/departamento
4. Completar la reserva y verificar que queda asociada al propietario seleccionado
5. Verificar que la reserva aparece en el listado del propietario

---

### #3 — Terminología inconsistente en tipos de área

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX / Producto

**Problema:**
Al crear/editar un área común hay 4 tipos: "gratuito", "mixto (compartido y exclusivo)", "de pago", "de pago con lista de invitados". La nomenclatura mezcla conceptos (gratis=compartido, exclusivo=pago).

**Causa raíz:**
El modelo de datos en backend usa un campo `type` (enum con 4 valores). El frontend muestra estos valores tal cual sin diferenciar dimensiones ortogonales.

**Solución:**

**Paso 1:** Revisar con negocio qué dimensiones aplicar:
   - ¿Gratis vs. De pago? (costo)
   - ¿Compartido vs. Exclusivo? (acceso)
   - ¿Requiere lista de invitados? (gestión)

**Paso 2:** Si se confirma dividir en atributos separados:
   - Modificar `createComunArea.vue` y `updateComunArea.vue` para usar checkboxes separados en vez de un único selector
   - Coordinar con backend para exponer los atributos como booleanos separados

**Paso 3:** Como solución temporal inmediata:
   - Renombrar las opciones en el frontend para que sean más claras:
     - "Gratuito (compartido)"
     - "Gratuito (exclusivo)"
     - "De pago (compartido)"
     - "De pago (exclusivo con lista de invitados)"

**Archivos a modificar:**
- `frontend/src/resources/view/admin/ComunAreas/createComunArea.vue` — opciones de tipo de área
- `frontend/src/resources/view/admin/ComunAreas/updateComunArea.vue` — opciones de tipo de área

**Validación:**
1. Ver que los nombres de tipo de área son claros y no se solapan
2. Confirmar con negocio que los cambios reflejan la realidad

---

### #4 — No se pueden configurar turnos horarios

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
La configuración de áreas solo permite un rango horario general (ej: 8am-8pm). No se puede dividir en bloques/turnos (ej: 6-8am, 8-10am).

**Causa raíz:**
`createComunArea.vue` y `updateComunArea.vue` usan un único `q-input` de hora inicio y hora fin.

**Solución:**

**Paso 1:** Agregar un componente de "bloques horarios" que permita agregar múltiples rangos:
   - Botón "Agregar turno"
   - Cada turno: hora_inicio + hora_fin + día(s) de la semana
   - Lista de turnos con botón de eliminar

**Paso 2:** Modificar el payload enviado al backend para incluir un array de bloques:
   ```json
   "time_slots": [
     { "day": 1, "start": "06:00", "end": "08:00" },
     { "day": 1, "start": "08:00", "end": "10:00" }
   ]
   ```

**Paso 3:** Coordinar con backend para que acepte este nuevo formato (probablemente el modelo `ComunArea` necesita un cambio en la DB).

**Archivos a modificar:**
- `frontend/src/resources/view/admin/ComunAreas/createComunArea.vue` — sección horarios
- `frontend/src/resources/view/admin/ComunAreas/updateComunArea.vue` — sección horarios
- `frontend/src/resources/services/store/comunArea.store.js` — modificar payload

**Validación:**
1. Crear área con múltiples turnos
2. Verificar que al crear una reserva solo se muestran los turnos disponibles
3. Editar área y verificar que los turnos se cargan correctamente

---

### #5 — No se puede poner un área en mantenimiento en fecha/horario específico

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
No hay forma de bloquear un área para una fecha y hora específica por mantenimiento. El `createMaintenance.vue` programa mantenimiento pero no bloquea automáticamente las reservas.

**Causa raíz:**
`createMaintenance.vue` envía los datos a `maintenanceStore.createMaintenance(payload)` que usa `POST /api/maintenances`. El backend probablemente guarda el registro pero **no cancela/bloquea las reservas existentes**.

**Solución:**

**Paso 1:** Revisar `createMaintenance.vue` para verificar que envía `date` y `duration` completos (ya lo hace: fecha, duración numérica y tipo).

**Paso 2:** Agregar selectores de hora de inicio y fin al formulario de mantenimiento:
   - Además de la fecha, agregar `hora_inicio` y `hora_fin` (o calcular desde duración)
   - Actualmente tiene `date`, `duration_value`, `duration_type`. Agregar `start_time` y `end_time`.

**Paso 3:** Modificar el payload para incluir `start_time` y `end_time`:
   ```js
   payload.append('start_time', formData.value.start_time)
   payload.append('end_time', formData.value.end_time)
   ```

**Paso 4:** Coordinar con backend para que:
   - Al crear un mantenimiento, cancele automáticamente las reservas existentes en ese rango
   - Bloquee la creación de nuevas reservas en ese rango
   - Exponga endpoint `GET /api/maintenances/by-area/{id}?date=` para consultar mantenimientos programados

**Paso 5:** En `bookingsList.vue` y `reservesPage.vue`, agregar indicador visual de que un área está en mantenimiento en determinada fecha/hora.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/ComunAreas/createMaintenance.vue` — agregar hora inicio/fin
- `frontend/src/resources/services/store/maintenance.store.js` — agregar acciones si faltan
- `frontend/src/resources/view/admin/ComunAreas/bookingsList.vue` — indicador de mantenimiento
- `frontend/src/resources/view/admin/reservesPage.vue` — indicador de mantenimiento

**Validación:**
1. Programar mantenimiento para un área en una fecha específica
2. Verificar que las reservas existentes en esa fecha se cancelan automáticamente
3. Intentar crear una nueva reserva en esa fecha/hora y verificar que está bloqueada
4. Verificar indicador visual de mantenimiento en los listados

---

**Siguiente archivo:** `02-reservas.md` — Hallazgos #6, #7, #8
