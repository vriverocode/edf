# Instrucciones — Finanzas: Gastos (#31, #32, #33, #34)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/Expenses/expensesList.vue`
- `frontend/src/resources/view/admin/Expenses/expenseForm.vue`
- `frontend/src/resources/components/finance/createProviderModal.vue`
- `frontend/src/resources/services/store/expense.store.js`
- `frontend/src/resources/services/store/provider.store.js`
- `frontend/src/resources/services/store/serviceCategory.store.js`

---

### #31 — No hay gestión real de proveedores

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
Al registrar un gasto, el proveedor se elige de una lista con solo nombre. Se puede crear uno nuevo directamente desde el formulario, pero no hay pantalla para gestionar proveedores (ver, editar, eliminar).

**Causa raíz:**
`provider.store.js` solo tiene `createProvider(payload)` → `POST /api/providers`. No tiene acciones para `getProviders`, `updateProvider`, `deleteProvider`.
El backend sí tiene controlador para proveedores con las rutas completas (GET, POST, PUT, DELETE).

**Solución:**

**Paso 1:** Completar el store `provider.store.js`:
```js
getProviders(params = {}) {
  return ApiService.get(`/api/providers${filterQuery(params)}`)
},
getProviderById(id) {
  return ApiService.get(`/api/providers/byId/${id}`)
},
updateProvider(id, payload) {
  return ApiService.post(`/api/providers/u/${id}`, payload)
},
deleteProvider(id) {
  return ApiService.delete(`/api/providers/d/${id}`)
}
```

**Paso 2:** Crear componente `providerList.vue`:
   - Lista paginada de proveedores con nombre, RUC, teléfono, email
   - Botones: editar (abre modal), eliminar (con confirmación)
   - Botón "Nuevo proveedor" (abre el modal existente `createProviderModal.vue`)

**Paso 3:** Agregar ruta en `routes/index.js`:
```js
{
  path: '/admin/providers/list',
  component: () => import('@/view/admin/Expenses/providerList.vue'),
  name: 'providerList',
  beforeEnter: [auth, role],
  meta: { title: 'PACIFIK', pagTitle: 'Proveedores', roles: ['admin'], depth: 2 }
}
```

**Paso 4:** Agregar enlace en el menú de finanzas (`financePage.vue`) o en el submenú de gastos.

**Archivos a modificar:**
- `frontend/src/resources/services/store/provider.store.js` — agregar getProviders, updateProvider, deleteProvider
- Crear `frontend/src/resources/view/admin/Expenses/providerList.vue` o `frontend/src/resources/components/finance/providerList.vue`
- `frontend/src/resources/routes/index.js` — ruta para lista de proveedores
- Opcional: `frontend/src/resources/view/admin/financePage.vue` — enlace

**Validación:**
1. Ir a Gestión de proveedores
2. Ver listado de proveedores existentes
3. Crear nuevo proveedor
4. Editar proveedor existente
5. Eliminar proveedor

---

### #32 — Mismo problema con categoría del servicio

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
La categoría del gasto tiene el mismo patrón que los proveedores: se crea al vuelo desde el formulario de gasto, sin pantalla de gestión.

**Causa raíz:**
`serviceCategory.store.js` solo tiene `getServiceCategories()` → `GET /api/service-categories` y `createServiceCategory(payload)` → `POST /api/service-categories`. No hay update/delete.

**Solución:**

**Paso 1:** Completar `serviceCategory.store.js`:
```js
updateServiceCategory(id, payload) {
  return ApiService.post(`/api/service-categories/u/${id}`, payload)
},
deleteServiceCategory(id) {
  return ApiService.delete(`/api/service-categories/d/${id}`)
}
```

**Paso 2:** Agregar gestión de categorías en la misma pantalla de gastos o crear un modal de administración:
   - Desde `expenseForm.vue`, en el selector de categoría, agregar botón "Gestionar categorías"
   - Modal con lista de categorías, botones editar/eliminar

**Archivos a modificar:**
- `frontend/src/resources/services/store/serviceCategory.store.js` — agregar update y delete
- `frontend/src/resources/view/admin/Expenses/expenseForm.vue` — modal de gestión de categorías

**Validación:**
1. Desde el formulario de gasto, acceder a gestión de categorías
2. Ver listado de categorías existentes
3. Crear, editar y eliminar categorías

---

### #33 — El comprobante adjunto no aparece en el listado de gastos

**Prioridad:** 🟠 Media
**Tipo:** Bug funcional

**Problema:**
Al registrar un gasto se puede adjuntar un comprobante, pero luego en el listado (`expensesList.vue`) no hay forma de verlo.

**Causa raíz:**
`expensesList.vue` no renderiza ni enlaza los archivos adjuntos. Puede que la respuesta del backend incluya `attachment_url` o similar pero el template no lo usa.

**Solución:**

**Paso 1:** Revisar la estructura de datos que devuelve `GET /api/expenses`. Buscar campo de attachment (puede ser `expense.attachment`, `expense.file_url`, `expense.receipt_url`).

**Paso 2:** En `expensesList.vue`, agregar una columna/icono de comprobante:
```html
<q-btn v-if="expense.attachment_url" icon="eva-attach-outline" flat color="primary" size="sm"
  @click="openAttachment(expense.attachment_url)">
  <q-tooltip>Ver comprobante</q-tooltip>
</q-btn>
```

**Paso 3:** Agregar método `openAttachment(url)` que abra el archivo en una nueva pestaña o en un modal:
```js
const openAttachment = (url) => {
  window.open(url, '_blank')
}
```

**Paso 4:** Si el backend no devuelve la URL del attachment en el listado, solo en el detalle, modificar el controlador de Laravel para incluirla.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Expenses/expensesList.vue` — columna de attachment
- Opcional: `frontend/src/resources/view/admin/Expenses/expenseForm.vue` — asegurar que se muestra preview del archivo subido

**Validación:**
1. Registrar un gasto con comprobante adjunto
2. Ir al listado de gastos
3. Verificar que aparece un icono/botón para ver el comprobante
4. Hacer clic y verificar que se abre el archivo

---

### #34 — Listado de gastos no tiene filtros rápidos

**Prioridad:** 🟡 Baja
**Tipo:** Mejora UX

**Problema:**
El listado de gastos (`expensesList.vue`) no tiene filtros rápidos accesibles directamente (por proveedor, categoría, rango de fechas).

**Causa raíz:**
`expensesList.vue` solo filtra por `month`, `year` y `status` (líneas 22-26). No tiene filtros para `provider_id`, `category_id`, `date_from`, `date_to`.

**Solución:**

**Paso 1:** En `expensesList.vue`, agregar filtros rápidos en la parte superior:
   - Selector de proveedor (`q-select` con opciones cargadas desde `providerStore.getProviders()`)
   - Selector de categoría (`q-select` con opciones desde `serviceCategoryStore.getServiceCategories()`)
   - Rango de fechas (date_from, date_to)

**Paso 2:** Agregar el filtro al objeto `filter`:
```js
const filter = ref({
  month: null,
  year: now.getFullYear(),
  status: null,
  provider_id: null,
  category_id: null,
  date_from: null,
  date_to: null
})
```

**Paso 3:** Modificar la llamada a `expenseStore.getExpenses(filters)` para que incluya los nuevos parámetros.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Expenses/expensesList.vue` — filtros rápidos adicionales
- `frontend/src/resources/services/store/expense.store.js` — asegurar que envía los nuevos parámetros al backend

**Validación:**
1. Ir a Gastos
2. Usar filtro por proveedor
3. Usar filtro por categoría
4. Usar rango de fechas
5. Verificar que los filtros funcionan en combinación

---

**Siguiente archivo:** `09-finanzas-cuotas.md` — Hallazgo #30
