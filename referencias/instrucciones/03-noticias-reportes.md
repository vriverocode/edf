# Instrucciones — Noticias y Reportes (#9, #10)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/Notices/noticesPage.vue`
- `frontend/src/resources/components/notices/createNoticeModal.vue`
- `frontend/src/resources/view/admin/Reports/reportBookings.vue`
- `frontend/src/resources/services/store/notice.store.js`
- `frontend/src/resources/services/store/report.store.js`

---

### #9 — Solo existen noticias masivas (no segmentadas)

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
Las noticias solo se pueden enviar a todo el edificio. No hay forma de segmentar por departamento, torre o piso.

**Causa raíz:**
El formulario de creación de noticia (`createNoticeModal.vue`) no tiene campos para seleccionar destinatarios específicos. El store `notice.store.js` envía el payload a `POST /api/notices` sin parámetros de segmentación.

**Solución:**

**Paso 1:** En `createNoticeModal.vue`, agregar un selector de segmentación:
   - Opción 1: "Todos los residentes" (comportamiento actual)
   - Opción 2: "Por torre" → selector de torres (cargar desde API)
   - Opción 3: "Por piso" → selector de pisos
   - Opción 4: "Por departamento" → selector de departamento específico (buscador)
   - Opción 5: "Por usuario" → selector de usuario (buscador)

**Paso 2:** Modificar el payload en `notice.store.js` → `createNotice(data)` para incluir:
   ```js
   payload: {
     ...noticeData,
     segment_type: 'all' | 'tower' | 'floor' | 'department' | 'user',
     segment_ids: [1, 2, 3] // IDs según el tipo de segmentación
   }
   ```

**Paso 3:** Coordinar con backend para que el endpoint `POST /api/notices` acepte estos nuevos parámetros y filtre los destinatarios al enviar notificaciones push.

**Paso 4:** En `noticesPage.vue`, agregar una columna "Segmentación" que muestre el alcance de cada noticia (Todos / Torre X / Piso Y / Depto #Z).

**Archivos a modificar:**
- `frontend/src/resources/components/notices/createNoticeModal.vue` — selector de segmentación
- `frontend/src/resources/components/notices/updateNoticeModal.vue` — selector de segmentación
- `frontend/src/resources/services/store/notice.store.js` — modificar payload
- `frontend/src/resources/view/admin/Notices/noticesPage.vue` — mostrar segmentación en listado

**Validación:**
1. Crear noticia seleccionando "Por torre" → elegir torre A
2. Verificar que solo los usuarios de torre A reciben la notificación
3. Crear noticia "Por departamento" → elegir depto 101
4. Verificar que solo el propietario de 101 recibe la notificación

---

### #10 — Reporte de reservas incluye canceladas sin separar

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
El reporte de reservas (`reportBookings.vue`) mezcla reservas canceladas con las demás, sin filtro para excluirlas ni indicador de que están canceladas.

**Causa raíz:**
`reportBookings.vue` usa `reportStore.getBookings(filters)` que a su vez llama `GET /api/reports/bookings?...` y el backend no filtra canceladas por defecto.

**Solución:**

**Paso 1:** En `reportBookings.vue`, agregar filtro visual "Incluir canceladas" (checkbox, desactivado por defecto).

**Paso 2:** Modificar el filtro por defecto para excluir reservas con `status = cancelled` (o el valor que use el backend, probablemente `is_cancelled = 1` o `status = 0`).

**Paso 3:** En el template, si se incluyen canceladas, mostrarlas con estilo visual diferente (tachado, badge rojo "Cancelada").

**Paso 4:** Modificar `reportStore.getBookings(filters)` para que envíe el parámetro `include_cancelled` al backend.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Reports/reportBookings.vue` — filtro "Incluir canceladas", estilo canceladas
- `frontend/src/resources/services/store/report.store.js` — parámetro include_cancelled

**Validación:**
1. Ir a Reportes → Reporte de reservas
2. Verificar que por defecto NO se muestran canceladas
3. Activar "Incluir canceladas" y verificar que aparecen
4. Verificar que las canceladas tienen un estilo diferente

---

**Siguiente archivo:** `04-usuarios.md` — Hallazgos #11, #12, #13, #14, #15, #16
