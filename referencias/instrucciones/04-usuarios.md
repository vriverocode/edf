# Instrucciones — Usuarios (#11, #12, #13, #14, #15, #16)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/Users/usersList.vue`
- `frontend/src/resources/view/admin/Users/createUser.vue`
- `frontend/src/resources/services/store/users.store.js`
- `frontend/src/resources/routes/index.js`

---

### #11 — Filtro por defecto "Propietarios" pero carga todos sin paginar

**Prioridad:** 🟠 Media
**Tipo:** Bug funcional / Performance

**Problema:**
El listado de usuarios (`usersList.vue`) arranca con `filterRol = 2` (Propietarios), `page = 1`, `search = ''`. Llama `userStore.getUsers(data)` que envía `GET /api/users?page=&search=&rol=2` pero no envía `per_page` ni límite.

**Causa raíz:**
`usersList.vue` línea 34-38: el objeto `data` solo incluye `page`, `search`, `rol` pero no `per_page`.
En `users.store.js`: `getUsers(data)` probablemente construye el query sin un límite de items por página.

**Solución:**

**Paso 1:** En `usersList.vue`, agregar `per_page` al objeto `data`:
```js
const data = {
  page: page.value,
  per_page: 20, // o un valor razonable
  search: search.value,
  rol: filterRol.value
}
```

**Paso 2:** Revisar `users.store.js` — acción `getUsers(data)` y asegurar que envía `per_page` al backend como query param. Si el backend no lo soporta, coordinarlo.

**Paso 3:** Agregar paginación en el template usando `<q-pagination>` (ya existe `lastPage` en la respuesta probablemente).

**Paso 4:** Si el backend no devuelve `last_page`/pagination info en la respuesta de `getUsers`, hay que modificarlo.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/usersList.vue` — agregar `per_page`, paginación visual
- `frontend/src/resources/services/store/users.store.js` — asegurar que envía y procesa paginación

**Validación:**
1. Cargar listado de usuarios con datos reales (>20 usuarios)
2. Verificar que solo muestra 20 por página
3. Navegar entre páginas
4. Cambiar filtro de rol y verificar que la paginación se resetea

---

### #12 — No hay filtros para buscar por departamento o nombre

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
No hay barra de búsqueda en `usersList.vue`. Existe variable `search` pero no hay input de búsqueda en el template.

**Causa raíz:**
`usersList.vue` línea 10: `const search = ref('')` — la variable existe pero nunca se usa en el template. No hay `<q-input>` de búsqueda.

**Solución:**

**Paso 1:** Agregar un input de búsqueda en el template de `usersList.vue`:
```html
<q-input v-model="search" dense debounce="500" placeholder="Buscar por nombre, email o departamento..."
  class="q-mx-md" @update:model-value="getUsers">
  <template v-slot:prepend>
    <q-icon name="eva-search-outline" />
  </template>
</q-input>
```

**Paso 2:** Agregar filtro por tipo de búsqueda: si el usuario escribe un número, buscar por departamento; si es texto, buscar por nombre. Modificar `getUsers` para enviar `searchType`:
```js
const data = {
  page: page.value,
  search: search.value,
  searchType: isNaN(search.value) ? 'name' : 'department',
  rol: filterRol.value
}
```

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/usersList.vue` — agregar input de búsqueda

**Validación:**
1. Escribir nombre de un usuario en la búsqueda
2. Verificar que filtra correctamente
3. Escribir número de departamento (ej: 101)
4. Verificar que busca por departamento

---

### #13 — Formulario de 2 pasos valida tarde y no marca obligatorios

**Prioridad:** 🟠 Media
**Tipo:** Bug funcional (UX de validación)

**Problema:**
`createUser.vue` tiene 2 pasos. Los campos obligatorios del paso 1 no se validan hasta llegar al paso 2. No hay indicador visual de qué campos son obligatorios.

**Causa raíz:**
`createUser.vue` línea 85-88:
```js
const createUser = () => {
  if (step.value == 0) {
    step.value++  // salta al paso 2 sin validar
    return
  }
```
El `@submit` del `q-form` solo valida al hacer submit final. Al hacer clic en "Siguiente" en paso 1, el `createUser` simplemente incrementa `step` sin forzar la validación del formulario.

**Solución:**

**Paso 1:** Modificar la lógica de `createUser` para validar antes de avanzar de paso:
```js
const nextStep = async () => {
  if (step.value == 0) {
    // Validar campos del paso 1 manualmente
    const errors = []
    if (!formData.value.name) errors.push('Nombre es requerido')
    if (!formData.value.username) errors.push('Usuario es requerido')
    if (!formData.value.email) errors.push('Email es requerido')
    if (!formData.value.password) errors.push('Contraseña es requerida')
    if (errors.length > 0) {
      showNotify('negative', errors.join('. '))
      return
    }
    step.value++
  }
}
```

**Paso 2:** Agregar indicador visual de campos obligatorios:
   - Agregar un `*` rojo al lado del label de cada campo obligatorio
   - Modificar los templates de los labels: `Nombre completo <span class="text-negative">*</span>`

**Paso 3:** En el `q-form`, separar la validación por pasos:
   - Paso 1: solo validar campos del paso 1
   - Paso 2: solo validar campos del paso 2
   - El submit final (desde paso 2) valida todo

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/createUser.vue` — validación por pasos, indicadores visuales

**Validación:**
1. Crear usuario, dejar campos vacíos en paso 1
2. Hacer clic en "Siguiente" — debe mostrar error inmediato en paso 1
3. Verificar que los campos obligatorios tienen marcador visual (*)
4. Completar todos los campos, llegar a paso 2, enviar — debe crear usuario

---

### #14 — Permite crear propietarios sin asignar unidad, y luego desaparecen

**Prioridad:** 🔴 Alta
**Tipo:** Bug funcional

**Problema:**
`createUser.vue` permite seleccionar "Propietario" como rol y dejar "Selecciona un departamento" (opción por defecto con `id: 0`). El usuario se crea pero luego no aparece en el listado de propietarios porque el filtro busca usuarios con departamento asignado.

**Causa raíz:**
`createUser.vue` línea 68-71: `apartment` por defecto tiene `id: 0`. En línea 91: `formData.value.idApartament = formData.value.apartment.id` envía `0` al backend.
El backend crea el usuario con rol propietario pero sin unidad asociada.
En `usersList.vue` o en el backend, al filtrar "Propietarios" probablemente excluye usuarios sin unidades (o el backend solo devuelve propietarios con unidades).

**Solución:**

**Paso 1:** Agregar validación: si `rol_id.id == 2` (Propietario) o `rol_id.id == 7` (Propietario Parcial) y `apartment.id == 0`, mostrar error y no permitir continuar:
```js
if ((formData.value.rol_id.id == 2 || formData.value.rol_id.id == 7) && formData.value.apartment.id == 0) {
  showNotify('negative', 'Los propietarios deben tener un departamento asignado')
  return
}
```

**Paso 2:** En el template de `createUser.vue`, si el rol seleccionado requiere unidad, mostrar el campo de departamento como obligatorio (con `*`).

**Paso 3:** Opcional: si se selecciona "Propietario" y no hay departamentos disponibles, mostrar un mensaje informativo y sugerir crear un departamento primero.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/createUser.vue` — validación de unidad requerida para propietarios

**Validación:**
1. Crear usuario con rol Propietario sin seleccionar departamento
2. Verificar que el formulario rechaza la creación con error
3. Crear usuario Propietario con departamento → debe crearse correctamente
4. Verificar que aparece en el listado de propietarios

---

### #15 — Filtros por inquilino/familiar/airbnb no muestran el departamento

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
Al filtrar usuarios por "Inquilino", "Familiar" o "Airbnb", cada fila solo muestra el nombre, sin indicar a qué departamento está asociado.

**Causa raíz:**
`usersList.vue` líneas 124-133:
```html
<div v-if="user.rol_id == 2 || user.rol_id == 7">
  <div>#{{ user.units.length > 0 ? user.formatted_units : 'Apt. no asignado' }}</div>
```
Solo muestra el departamento para `rol_id == 2` (Propietario) y `rol_id == 7` (Propietario Parcial). Para inquilinos (3), familiar (4) y airbnb (5), no se renderiza.

**Solución:**

**Paso 1:** Modificar la condición en `usersList.vue` para que también muestre el departamento para roles 3, 4, 5:
```html
<div v-if="user.rol_id == 2 || user.rol_id == 7 || user.rol_id == 3 || user.rol_id == 4 || user.rol_id == 5">
```

**Paso 2:** Si los usuarios con esos roles no tienen `units` directamente, investigar cómo viene la data del backend. Puede ser que tengan `apartment` o `department` en la respuesta. Revisar la estructura de `user` en la respuesta de `GET /api/users`.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/usersList.vue` — línea 124, agregar roles 3, 4, 5

**Validación:**
1. Filtrar por Inquilino
2. Verificar que cada inquilino muestra el departamento asociado
3. Filtrar por Familiar, verificar lo mismo
4. Filtrar por Airbnb, verificar lo mismo

---

### #16 — "Editar usuario" y "Ver pagos" no funcionan

**Prioridad:** 🔴 Alta
**Tipo:** Bug funcional

**Problema:**
Los botones de acción "Editar usuario" (icono `eva-settings-outline`) y "Ver pagos" (icono `eva-credit-card-outline`) en `usersList.vue` no responden al clic. No hay `@click` handler.

**Causa raíz:**
`usersList.vue` líneas 153-166:
```html
<div>
  <q-btn icon="eva-settings-outline" ...>
    <!-- SIN @click -->
  </q-btn>
</div>
<div>
  <q-btn icon="eva-credit-card-outline" ...>
    <!-- SIN @click -->
  </q-btn>
</div>
```
Los botones están renderizados visualmente pero no tienen evento `@click`.

**Solución:**

**Paso 1:** Agregar `@click` al botón "Editar usuario" (línea 154):
```html
<q-btn icon="eva-settings-outline" class="mx-1" color="primary" flat size="0.9rem"
  @click="goTo('/admin/users/form/update/' + user.id)">
```
Nota: La ruta `/admin/users/form/update/:id` puede no existir en el router. Si no existe, hay que:
   a. Crear la ruta en `routes/index.js` apuntando a un componente de edición (puede ser `createUser.vue` reutilizado con datos precargados), O
   b. Apuntar a una ruta existente como `/admin/users/assign-property/:id` para gestión de propiedades

**Paso 2:** Agregar `@click` al botón "Ver pagos" (línea 161):
```html
<q-btn icon="eva-credit-card-outline" class="mx-1" color="amber-6" flat size="0.9rem"
  @click="goTo('/admin/pays/user/' + user.id)">
```
Nota: La ruta `/admin/pays/user/:id` puede no existir. Alternativas:
   a. Crear una nueva ruta en el router
   b. Redirigir a `/admin/pays/maintenance?user_id=` (listado de pagos filtrado por usuario)

**Paso 3:** Crear las rutas faltantes en `routes/index.js`:
```js
{
  path: '/admin/users/form/update/:id',
  component: () => import('@/view/admin/Users/createUser.vue'),
  name: 'usersUpdate',
  beforeEnter: [auth, role],
  meta: { title: 'Bienvenido', pagTitle: 'Editar usuario', roles: ['admin', 'super-admin'], depth: 3 }
}
```

**Archivos a modificar:**
- `frontend/src/resources/view/admin/Users/usersList.vue` — agregar `@click` handlers en líneas 154 y 161
- `frontend/src/resources/routes/index.js` — agregar ruta para editar usuario (si no existe)

**Validación:**
1. Hacer clic en "Editar usuario" — debe navegar a la página de edición de ese usuario
2. Hacer clic en "Ver pagos" — debe navegar al listado de pagos del usuario
3. Verificar que ambos botones funcionan para cualquier usuario en la lista

---

**Siguiente archivo:** `05-departamentos.md` — Hallazgos #17, #18, #19, #20, #21, #22, #23
