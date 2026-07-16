# Instrucciones — Departamentos / Unidades (#17, #18, #19, #20, #21, #22, #23)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/Department/departmentList.vue`
- `frontend/src/resources/view/admin/Department/updateDepartment.vue`
- `frontend/src/resources/view/admin/Users/assignPropertyPage.vue`
- `frontend/src/resources/components/admin/users/modal/assignUnitModal.vue`
- `frontend/src/resources/components/admin/initialWaterReadingModal.vue`
- `frontend/src/resources/view/admin/WaterReadings/waterReadingsList.vue`
- `frontend/src/resources/view/admin/WaterReadings/waterReadingForm.vue`
- `frontend/src/resources/services/store/apartment.store.js`
- `frontend/src/resources/services/store/user.store.js`
- `frontend/src/resources/services/store/waterReadings.store.js`
- `frontend/src/resources/view/admin/financePage.vue` (menú "Departamentos")

---

### #17 — Menú "Departamentos" gestiona todo tipo de unidades

**Prioridad:** 🟡 Baja
**Tipo:** Mejora UX / naming

**Problema:**
El menú se llama "Departamentos" pero desde ahí también se gestionan estacionamientos y depósitos.

**Causa raíz:**
`usersPage.vue` (menú de usuarios) tiene el botón "Departamentos" que apunta a `/admin/department/list`. El nombre no refleja que incluye estacionamientos y depósitos.

**Solución:**

**Paso 1:** Cambiar el texto del botón en `usersPage.vue` de "Departamentos" a "Unidades" o "Inmobiliarios".

**Paso 2:** Cambiar el `pagTitle` en la ruta `/admin/department/list` en `routes/index.js` de "Departamentos" a "Unidades".

**Paso 3:** Mantener la URL como está (`/admin/department/list`) para no romper enlaces existentes.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/usersPage.vue` — texto del botón
- `frontend/src/resources/routes/index.js` — `pagTitle` de la ruta `departmentList`

**Validación:**
1. El menú ahora dice "Unidades" en lugar de "Departamentos"
2. El título de la página dice "Unidades"

---

### #18 — Listado de unidades no muestra el propietario

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
`departmentList.vue` muestra las unidades pero no aparece el nombre del propietario actual de cada una. Hay que entrar al detalle para saberlo.

**Causa raíz:**
El template de `departmentList.vue` no extrae ni muestra el campo de propietario de la unidad. La respuesta del backend (`GET /api/apartments`) probablemente incluye `owner_name` o `user.name`.

**Solución:**

**Paso 1:** Revisar la estructura de datos que devuelve `GET /api/apartments`. Buscar qué campo trae el propietario (puede ser `apartment.owner?.name`, `apartment.user?.name`, `apartment.owner_name`).

**Paso 2:** Agregar una columna/línea en el template de `departmentList.vue` que muestre el propietario:
```html
<div class="text-body2 text-grey-7">
  Propietario: {{ apartment.owner?.name || apartment.user?.name || 'Sin asignar' }}
</div>
```

**Paso 3:** Si el backend no devuelve el propietario en el listado, se necesita modificar el controlador de Laravel para incluirlo (hacer `->with('user')` en la query de `Apartment`).

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Department/departmentList.vue` — agregar visualización del propietario

**Validación:**
1. Ir a Unidades
2. Verificar que cada unidad muestra el nombre del propietario actual
3. Las unidades sin propietario deben mostrar "Sin asignar"

---

### #19 — % de participación solo se asigna al departamento

**Prioridad:** 🔴 Alta
**Tipo:** Bug de datos (a confirmar con negocio)

**Problema:**
El porcentaje de participación (que determina cuánto paga cada propietario) solo se asigna al departamento. Estacionamientos y depósitos quedan en 0%. El % de participación del propietario debería ser la suma de todas sus unidades.

**Causa raíz:**
En `createUnit.vue` y `updateDepartment.vue`, el campo `percentage` (participación) probablemente solo se muestra/edita cuando `type == 1` (departamento). Para estacionamientos y depósitos, el campo está oculto o se envía como 0.

**Solución:**

**Paso 1:** Confirmar con negocio la regla. Si se confirma que debe sumar:
   - En `createUnit.vue`, mostrar el campo de porcentaje para todos los tipos de unidad (no solo departamentos).

**Paso 2:** En `updateDepartment.vue`, permitir editar el porcentaje para estacionamientos y depósitos.

**Paso 3:** En `assignPropertyPage.vue`, al mostrar las unidades de un propietario, agregar un campo "Participación total" que sume los porcentajes de todas las unidades del mismo propietario.

**Paso 4:** Coordinar con backend para que el cálculo de cuotas use la suma de porcentajes de todas las unidades del propietario.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Department/createUnit.vue` — mostrar porcentaje para todos los tipos
- `frontend/src/resources/view/admin/Department/updateDepartment.vue` — mostrar porcentaje para todos los tipos
- `frontend/src/resources/view/admin/Users/assignPropertyPage.vue` — suma de participaciones

**Validación:**
1. Crear un estacionamiento con porcentaje de participación > 0
2. Asignarlo a un propietario que ya tiene un departamento
3. Verificar que la suma de participaciones del propietario es correcta

---

### #20 — Modal de reasignación sin buscador

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
El modal para reasignar un propietario a una unidad muestra todos los usuarios solo con nombre, sin campo de búsqueda.

**Causa raíz:**
`departmentList.vue` líneas 60-78: `openChangeOwnerModal` carga `ownersWithoutApartment` desde `apartmentStore.getOwnersWithoutApartment()`. Luego en el template se muestra la lista completa sin filtro.

**Solución:**

**Paso 1:** Modificar el modal de cambio de propietario en `departmentList.vue`:
   - Agregar un `<q-input>` de búsqueda que filtre `ownersWithoutApartment` por nombre
   - Usar `computed` para filtrar en tiempo real:
   ```js
   const ownerSearch = ref('')
   const filteredOwners = computed(() => {
     return ownersWithoutApartment.value.filter(o =>
       o.name.toLowerCase().includes(ownerSearch.value.toLowerCase())
     )
   })
   ```

**Paso 2:** En el template del modal, reemplazar el `v-for="owner in ownersWithoutApartment"` con `v-for="owner in filteredOwners"`.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Department/departmentList.vue` — buscador en modal

**Validación:**
1. Abrir modal de reasignación de propietario
2. Escribir nombre parcial en el buscador
3. Verificar que filtra correctamente
4. Seleccionar un propietario de la lista filtrada

---

### #21 — Registrar consumo de agua es impráctico (unidad por unidad)

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
Para registrar lecturas de agua, el admin debe hacer clic en cada unidad individualmente desde `departmentList.vue` (botón "lectura inicial de agua") o desde `waterReadingsList.vue` → `waterReadingForm.vue`. Con ~180 unidades es inmanejable.

**Causa raíz:**
No existe un flujo secuencial que permita ir unidad por unidad sin volver al listado. `waterReadingForm.vue` registra una sola lectura y al guardar redirige al listado.

**Solución:**

**Paso 1:** Modificar `waterReadingForm.vue` para que después de guardar, pueda avanzar automáticamente a la siguiente unidad sin departamento registrado en el período.

**Paso 2:** Agregar en el store `waterReadings.store.js` una acción que obtenga la próxima unidad sin lectura:
```js
getNextPendingDepartment(month, year, currentDepartmentId) {
  return ApiService.get(`/api/water-readings/next-pending?month=${month}&year=${year}&after=${currentDepartmentId}`)
}
```

**Paso 3:** En `waterReadingForm.vue`, después del guardado exitoso:
   - Consultar si hay siguiente unidad pendiente
   - Si sí: redirigir a `waterReadingForm` con el ID de la siguiente unidad + prefilled el mes/año
   - Si no: mostrar notificación "Todas las lecturas del período están registradas" y redirigir al listado

**Paso 4:** Agregar navegación: botones "Siguiente unidad" y "Unidad anterior" en el formulario.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/WaterReadings/waterReadingForm.vue` — flujo secuencial
- `frontend/src/resources/services/store/waterReadings.store.js` — acción `getNextPendingDepartment`
- `frontend/src/resources/routes/index.js` — ruta para modo secuencial (opcional)

**Validación:**
1. Registrar lectura para unidad 101
2. Verificar que automáticamente carga la siguiente unidad sin lectura
3. Continuar hasta completar todas
4. Verificar que al final muestra mensaje de "completado"

---

### #22 — No hay indicador de qué unidades ya tienen lectura registrada

**Prioridad:** 🔴 Alta
**Tipo:** Mejora UX

**Problema:**
Tanto en `departmentList.vue` como en `waterReadingsList.vue`, no hay indicador visual de qué unidades ya tienen la lectura de agua registrada para el período actual.

**Causa raíz:**
`departmentList.vue` usa `apartmentStore.getPaginationApartment(data)` que no incluye información sobre lecturas de agua. El template no tiene lógica para mostrar si ya se registró.

**Solución:**

**Paso 1:** En `departmentList.vue`, después de cargar las unidades, hacer una llamada adicional para obtener las unidades que ya tienen lectura en el período actual:
```js
const getDepartmentsWithReading = async (month, year) => {
  const response = await waterReadingsStore.getWaterReadings({ month, year, per_page: 999 })
  if (response?.code === 200) {
    departmentsWithReading.value = response.data.pagination.data.map(r => r.apartment_id)
  }
}
```

**Paso 2:** En el template de `departmentList.vue`, agregar un badge/icono:
```html
<q-icon v-if="departmentsWithReading.includes(apartment.id)" name="eva-checkmark-circle-2" color="positive" size="sm">
  <q-tooltip>Lectura de agua registrada este período</q-tooltip>
</q-icon>
```

**Paso 3:** En `waterReadingsList.vue`, ya existe la lógica de fetch por mes/año. Agregar columna visual "Registrada" con check/tiempo desde que se registró.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Department/departmentList.vue` — indicador de lectura registrada
- `frontend/src/resources/view/admin/WaterReadings/waterReadingsList.vue` — columna de estado

**Validación:**
1. Registrar lectura de agua para una unidad desde el período actual
2. Volver al listado de unidades
3. Verificar que esa unidad muestra indicador de "lectura registrada"
4. Verificar que otras unidades no lo muestran

---

### #23 — Editar unidad abre página nueva y pierde contexto

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
El botón "Editar" en `departmentList.vue` navega a `/admin/apartments/edit/:id` (página completa). Al volver, el listado se recarga desde el inicio y no hay indicador de qué cambió.

**Causa raíz:**
`departmentList.vue` usa `goTo('/admin/apartments/edit/' + apartment.id)` que navega a `updateDepartment.vue`. Es una página independiente, no un modal.

**Solución:**

**Opción A (Convertir a modal):**
- Reemplazar la navegación por un modal de edición
- Crear un componente `EditDepartmentModal.vue` que contenga el formulario de `updateDepartment.vue`
- Al cerrar el modal, refrescar el listado manteniendo la posición

**Opción B (Mantener página pero mejorar UX):**
- En `departmentList.vue`, antes de navegar, guardar la página actual en `localStorage` o `sessionStorage`
- En `updateDepartment.vue`, al guardar/volver, redirigir a `departmentList` con el parámetro de página y un highlight del registro modificado
- Pasar `?page=X&highlight=Y` como query params

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Department/departmentList.vue` — guardar estado antes de navegar
- `frontend/src/resources/view/admin/Department/updateDepartment.vue` — redirigir con contexto
- O crear nuevo componente modal

**Validación:**
1. Ir a página 3 del listado de unidades
2. Editar una unidad
3. Volver: debe mantener la página 3 y mostrar highlight en la unidad editada
4. (Si es modal: editarla en modal, cerrar, y ver el cambio inmediatamente sin recargar)

---

**Siguiente archivo:** `06-finanzas-balance-pagos.md` — Hallazgos #24, #35, #36, #37, #38, #39, #40
