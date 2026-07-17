# Guía de Validación — Frontend

Este documento resume todos los cambios implementados en el frontend, con rutas para testear cada funcionalidad y criterios de validación.

---

## Módulo 01 — Áreas Comunes (#1, #2, #3)

### #1 — Lista de reservas (BookingsList.vue)

**Ruta:** `/admin/comun-area/bookings/:id/list`

**Qué se hizo:**
- Se reescribió `bookingsList.vue` con:
  - Paginación
  - Filtros por mes y año
  - Checkbox para ver/ocultar canceladas
  - Número de departamento visible en cada reserva
  - Estilo de opacidad para canceladas

**Validación:**
1. Ir a Áreas comunes → seleccionar un área → "Ver reservas"
2. Verificar paginación (icono de flechas abajo)
3. Cambiar mes/año en los filtros
4. Activar/desactivar "Mostrar canceladas"
5. Verificar que se ve el número de depto en cada reserva

### #2 — Modal de selección de propietario

**Ruta:** `/admin/comun-area/bookings/:id/list`

**Qué se hizo:**
- Al hacer clic en "Crear reserva", se abre un modal para seleccionar el propietario antes de crear la reserva

**Validación:**
1. Desde la lista de reservas, hacer clic en "Crear reserva"
2. Verificar que aparece modal de selección de propietario
3. Seleccionar un propietario y continuar
4. Verificar que la reserva se crea correctamente

### #3 — Tipos de área renombrados

**Rutas:**
- `/admin/comun-area/form/add`
- `/admin/comun-area/form/update/:id`

**Qué se hizo:**
- Las opciones de tipo de área en los formularios se renombraron para ser más descriptivas:
  - "Gratuito (compartido)"
  - "Gratuito (exclusivo)"
  - "De pago (compartido)"
  - "De pago (exclusivo con lista de invitados)"

**Validación:**
1. Ir a crear/editar área común
2. Verificar que las opciones del selector de tipo son claras

---

## Módulo 02 — Reservas (#6, #7, #8)

### #6 — Filtros de pago

**Ruta:** `/admin/pays/booking`

**Qué se hizo:**
- Filtros rápidos por tipo de pago (Sin pago / Pendiente / Aprobado / Exitoso) en `reservesPage.vue`
- Banner que muestra "X pagos pendientes de aprobación"
- Indicadores visuales de estado de pago (colores)

**Validación:**
1. Ir a Pagos → Pago de reservas
2. Usar los filtros rápidos de tipo de pago
3. Verificar que los colores de estado de pago son distintos para cada estado

### #7 — Flujo de devolución

**Ruta:** `/admin/pay/validate/:id`

**Qué se hizo:**
- En `reservesPage.vue`: las reservas canceladas muestran badge "Devuelto" si ya se procesó la devolución
- En `validatePay.vue`: se agregó sección "Devoluciones" que permite registrar una devolución vía `POST /api/pays/refund`

**Validación:**
1. Ir a una reserva cancelada con pago aprobado
2. Ver que aparece un botón/opción para registrar devolución
3. Registrar una devolución y verificar que aparece en el histórico

### #8 — Paginación + número de departamento + estilo canceladas

**Ruta:** `/admin/pays/booking`

**Qué se hizo:**
- Paginación agregada en `reservesPage.vue`
- Columna "Depto" agregada
- Las reservas canceladas se muestran con opacidad reducida

**Validación:**
1. Ir a Pagos → Pago de reservas
2. Verificar paginación
3. Localizar una reserva cancelada y verificar que tiene estilo diferente (opacidad)

---

## Módulo 03 — Noticias y Reportes (#9, #10)

### #9 — Segmentación de noticias

**Rutas:**
- `/admin/notices` (listado)
- Crear/editar noticia vía modal

**Qué se hizo:**
- `createNoticeModal.vue` y `updateNoticeModal.vue`: se agregó selector de segmentación con opciones:
  - Todos (sin segmentación)
  - Por torre → selector de torres
  - Por piso → selector de pisos
  - Por departamento → buscador de departamentos
  - Por usuario → buscador de usuarios
- `noticesList.vue`: se agregó badge mostrando la segmentación de cada noticia (ej: "Torre: 1" o "Depto: 101")

**Validación:**
1. Ir a Noticias → Crear noticia
2. Seleccionar segmentación "Por torre" y elegir una torre
3. Crear la noticia
4. En el listado de noticias, verificar que se muestra la segmentación
5. Editar la noticia y cambiar la segmentación

### #10 — Reporte de reservas con filtro canceladas

**Ruta:** `/admin/reports/bookings`

**Qué se hizo:**
- Checkbox "Incluir canceladas" agregado en `reportBookings.vue`
- `report.store.js` envía `include_cancelled=1` al backend cuando está activado
- Las canceladas se excluyen por defecto

**Validación:**
1. Ir a Reportes → Reporte de reservas
2. Verificar que por defecto no se muestran canceladas
3. Activar "Incluir canceladas"
4. Verificar que aparecen reservas canceladas

---

## Módulo 04 — Usuarios (#11-#16)

### #11 — Búsqueda en lista de usuarios

**Ruta:** `/admin/users/list`

**Qué se hizo:**
- Buscador por nombre, email, documento en `usersList.vue`

**Validación:**
1. Ir a Usuarios
2. Escribir en el buscador
3. Verificar que los resultados se filtran

### #12 — Paginación en usuarios

**Ruta:** `/admin/users/list`

**Qué se hizo:**
- Paginación agregada a la lista de usuarios

**Validación:**
1. Ir a Usuarios
2. Verificar paginación al final de la lista

### #13 — Campos obligatorios con indicación

**Ruta:** `/admin/users/form/add`

**Qué se hizo:**
- Los campos obligatorios en `createUser.vue` y otros formularios ahora muestran un asterisco rojo

**Validación:**
1. Ir a crear usuario
2. Verificar que los campos requeridos tienen asterisco (*)

### #14 — Edición inline de campos

**Ruta:** `/admin/users/list`

**Qué se hizo:**
- Se puede editar nombre, email y rol directamente desde la lista

**Validación:**
1. Ir a Usuarios
2. Hacer clic en el icono de editar
3. Cambiar el nombre y guardar

### #15 — Búsqueda en roles

**Ruta:** `/admin/users/list`

**Qué se hizo:**
- Filtro por rol (select) agregado

**Validación:**
1. Ir a Usuarios
2. Seleccionar un rol específico en el filtro
3. Verificar que solo se muestran usuarios de ese rol

### #16 — Bug de roles corregido

**Archivo:** `services/store/user.store.js`

**Qué se hizo:**
- Se corrigió la obtención de roles para que funcione correctamente

**Validación:**
1. Ir a Usuarios
2. Verificar que los roles se cargan correctamente

---

## Módulo 05 — Departamentos (#17-#23)

### #17 — "Departamentos" → "Unidades"

**Ruta:** `/admin/department/list`

**Qué se hizo:**
- Se renombraron todas las referencias de "Departamentos" a "Unidades" en `usersPage.vue` y las rutas correspondientes

**Validación:**
1. Ir a la página de usuarios de un propietario
2. Verificar que dice "Unidades" en lugar de "Departamentos"

### #18 — Nombre del propietario visible

**Ruta:** `/admin/department/list`

**Qué se hizo:**
- En `departmentList.vue` se agrega columna con nombre del propietario de cada unidad

**Validación:**
1. Ir a Unidades
2. Verificar que se ve el nombre del propietario

### #19 — Porcentaje de propiedad

**Ruta:** `/admin/department/list` (detalle)

**Qué se hizo:**
- El porcentaje de participación ahora se muestra para todos los tipos de unidad
- En `assignPropertyPage.vue` se muestra la suma de porcentajes

**Validación:**
1. Ir a detalle de propietario
2. Verificar que cada unidad muestra su porcentaje

### #20 — Búsqueda en reasignación

**Ruta:** `/admin/users/assign-property/:id`

**Qué se hizo:**
- Buscador en el modal de reasignación de propietario

**Validación:**
1. Ir a asignar propiedad a un usuario
2. Buscar un propietario
3. Verificar que se filtran resultados

### #21 — Lectura secuencial de agua

**Ruta:** `/admin/water_readings/form/add`

**Qué se hizo:**
- Flujo secuencial de lectura de agua: al terminar una lectura, avanza automáticamente al siguiente departamento

**Validación:**
1. Ir a Registrar medición de agua
2. Completar una lectura
3. Verificar que avanza al siguiente depto automáticamente

### #22 — Badge de lectura pendiente

**Ruta:** `/admin/department/list`

**Qué se hizo:**
- Indicador visual (badge) que muestra si una unidad tiene lectura de agua pendiente

**Validación:**
1. Ir a Unidades
2. Verificar que las unidades con lectura pendiente muestran un badge

### #23 — Contexto de lista al editar

**Ruta:** `/admin/department/list` → editar

**Qué se hizo:**
- Al guardar la edición de un departamento, se retorna a la misma página y posición de la lista

**Validación:**
1. Ir a Unidades, ir a página 3
2. Editar un departamento, guardar
3. Verificar que vuelve a la página 3

---

## Módulo 06 — Finanzas Balance/Pagos (#35, #24, #36, #37, #38, #39, #40)

### #24 — Placeholder de balance corregido

**Ruta:** `/admin/finance`

**Qué se hizo:**
- Se corrigió el placeholder que mostraba datos incorrectos

### #35 — Tope de deuda en morosos

**Ruta:** `/admin/reports/delinquents`

**Qué se hizo:**
- En `debtorList.vue`: se agregó columna "Monto a pagar" con el total de deuda pendiente

**Validación:**
1. Ir a Reportes → Reporte de morosos
2. Verificar que se muestra el monto total adeudado

### #36 — Enlace Presupuesto anual movido

**Ruta:** `/admin/monthly_bills/menu`

**Qué se hizo:**
- El enlace "Presupuesto anual" se movió de `financePage.vue` a `monthlyBillsMenu.vue`

**Validación:**
1. Ir a Finanzas → Presupuesto
2. Verificar que el enlace "Presupuesto anual" está en el menú de presupuesto

### #37 — Exportar reporte de gastos a Excel

**Ruta:** `/admin/reports/expenses`

**Qué se hizo:**
- Botón "Exportar Excel" en `reportExpenses.vue`

**Validación:**
1. Ir a Reportes → Gastos
2. Hacer clic en Exportar Excel
3. Verificar que se descarga un archivo .xlsx

### #38 — Exportar reporte de morosos a Excel

**Ruta:** `/admin/reports/delinquents`

**Qué se hizo:**
- Botón "Exportar Excel" en `delinquentReport.vue`

**Validación:**
1. Ir a Reportes → Morosos
2. Hacer clic en Exportar Excel
3. Verificar que se descarga un archivo .xlsx

### #39 — Exportar reporte de reservas a PDF

**Ruta:** `/admin/reports/bookings`

**Qué se hizo:**
- Botón "Exportar PDF" en `reportBookings.vue`

**Validación:**
1. Ir a Reportes → Reservas
2. Hacer clic en Exportar PDF
3. Verificar que se descarga un archivo .pdf

### #40 — Dashboard financiero

**Ruta:** `/admin/finance`

**Qué se hizo:**
- Indicadores financieros en la página de finanzas

**Validación:**
1. Ir a Finanzas
2. Verificar que se muestran indicadores (totales, etc.)

---

## Módulo 07 — Presupuesto (#25-#29)

### #25 — Máscara de montos corregida

**Ruta:** `/admin/monthly_bills/form/add` y `edit/:id`

**Qué se hizo:**
- Corrección de la máscara de entrada de montos en `monthlyBillsForm.vue`

**Validación:**
1. Ir a crear/editar presupuesto mensual
2. Ingresar un monto con decimales
3. Verificar que el formato es correcto

### #26 — Consumo del mes anterior + validación de anomalía

**Ruta:** `/admin/monthly_bills/form/add`

**Qué se hizo:**
- Muestra el consumo de agua del mes anterior para referencia
- Valida si hay una anomalía (consumo muy diferente al mes anterior)

**Validación:**
1. Ir a crear presupuesto mensual
2. Verificar que se muestra el consumo del mes anterior
3. Si el consumo varía mucho, verificar que se muestra una advertencia

### #27 — Modal de gastos con mes/año fijo

**Ruta:** `/admin/monthly_bills/form/add` (en paso de gastos)

**Qué se hizo:**
- El modal `includeExpensesModal.vue` ahora bloquea mes y año al período actual del presupuesto

**Validación:**
1. Ir a crear presupuesto mensual
2. Ir al paso de gastos incluidos
3. Verificar que mes y año están fijos

### #28 — Barra de progreso de presupuesto

**Ruta:** `/admin/monthly_bills/form/add` (detalle)

**Qué se hizo:**
- Barra de progreso visual que muestra cuánto del presupuesto se ha gastado en `monthlyBillsForm.vue`

**Validación:**
1. Ir a crear/editar presupuesto
2. Verificar la barra de progreso que muestra gasto vs presupuesto

### #29 — Botón secuencial en WaterReadingsList

**Ruta:** `/admin/water_readings/list`

**Qué se hizo:**
- Botón para avanzar secuencialmente entre lecturas pendientes
- Contador de progreso (ej: "5/180")

**Validación:**
1. Ir a Mediciones de agua
2. Verificar contador y botón secuencial

---

## Módulo 08 — Gastos (#31-#34)

### #31 — Gestión de proveedores

**Ruta:** `/admin/providers/list`

**Qué se hizo:**
- Se completó `provider.store.js` con acciones: `getProviders`, `getProviderById`, `updateProvider`, `deleteProvider`
- Se creó `providerList.vue` con:
  - Lista paginada en tabla (nombre, RUC, categoría, teléfono, email)
  - Buscador
  - Botón "Nuevo proveedor" (abre modal existente)
  - Botón editar (abre modal pre-cargado)
  - Botón eliminar (con confirmación)
- Ruta agregada: `/admin/providers/list`
- Enlace agregado en menú de finanzas (`financePage.vue`)

**Validación:**
1. Ir a Finanzas → Proveedores
2. Ver listado de proveedores existentes
3. Usar el buscador
4. Crear nuevo proveedor (botón "Nuevo proveedor")
5. Editar un proveedor existente
6. Eliminar un proveedor (con confirmación)
7. Verificar paginación

### #32 — Gestión de categorías de servicio

**Ruta:** `/admin/expenses/form/add` (editar gasto)

**Qué se hizo:**
- Se completó `serviceCategory.store.js` con acciones: `updateServiceCategory`, `deleteServiceCategory`
- En `expenseForm.vue`, se agregó botón "Gestionar categorías de servicio" en el paso 2
- Modal de gestión con lista, editar y eliminar categorías

**Validación:**
1. Ir a Gastos → Registrar gasto
2. En paso 2, hacer clic en "Gestionar categorías de servicio"
3. Ver listado de categorías
4. Editar una categoría
5. Eliminar una categoría
6. Verificar que los cambios se reflejan

### #33 — Comprobante adjunto en listado de gastos

**Ruta:** `/admin/expenses/list`

**Qué se hizo:**
- Se agregó icono de adjunto (clip) en cada gasto que tenga `attachment_url`
- Al hacer clic, abre el comprobante en nueva pestaña

**Validación:**
1. Ir a Gastos
2. Registrar un gasto con comprobante adjunto
3. Ir al listado de gastos
4. Verificar que aparece el icono de adjunto
5. Hacer clic y verificar que se abre el comprobante

### #34 — Filtros rápidos en gastos

**Ruta:** `/admin/expenses/list`

**Qué se hizo:**
- Se agregaron filtros al diálogo de filtros de gastos:
  - Selector de proveedor
  - Selector de categoría
  - Rango de fechas (desde/hasta)
- `expense.store.js` ahora envía `provider_id`, `category_id`, `date_from`, `date_to`

**Validación:**
1. Ir a Gastos
2. Abrir el diálogo de filtros (icono de embudo)
3. Seleccionar un proveedor y aplicar
4. Seleccionar una categoría
5. Usar rango de fechas
6. Verificar que los filtros funcionan combinados
7. Limpiar filtros

---

## Módulo 09 — Cuotas (#30)

### #30 — Botón "Generar cuotas del mes"

**Ruta:** `/admin/monthly_bills/view/:id`

**Qué se hizo:**
- Nueva acción `generateMonthlyQuotas(month, year)` en `quota.store.js`
- Botón "Generar cuotas" en `monthlyBillsDetails.vue` que llama a `POST /api/quotas/generate?month=X&year=Y`

**Validación:**
1. Ir a Presupuesto → Listado → Ver detalles de un presupuesto mensual
2. Verificar que aparece el botón "Generar cuotas"
3. Hacer clic y verificar que se generan las cuotas
4. Ir a Cuotas → Cuotas de mantenimiento para verificar

---

## Módulo 10 — General (#41)

### #41 — Corrección de profundidades (depth) en rutas

**Archivo:** `routes/index.js`

**Qué se hizo:**
- Se corrigieron los siguientes depth incorrectos para que la navegación y botón "Volver" del layout funcionen correctamente:

| Ruta | Depth anterior | Depth nuevo |
|------|:---:|:---:|
| `/admin/quotas/maintenance/list` | 1 | 2 |
| `/admin/water_readings/list` | 4 | 2 |
| `/admin/water_readings/form/add` | 5 | 3 |
| `/admin/water_readings/view/:id` | 5 | 3 |
| `/admin/water_readings/edit/:id` | 5 | 3 |
| `/admin/expenses/form/add` | 4 | 3 |
| `/admin/expenses/edit/:id` | 4 | 3 |
| `/admin/financial-accounts/add` | 2 | 3 |
| `/admin/financial-accounts/update/:id` | 2 | 3 |
| `/admin/events/view/:id` | 2 | 3 |
| `/admin/monthly_bills/edit/:id` | 5 | 4 |

**Validación:**
1. Navegar por las rutas listadas
2. Verificar que el botón "Volver" del layout (panelLayout) aparece/desaparece adecuadamente
3. No debe haber botones "Volver" duplicados en las vistas

---

## Módulos bloqueados (#4, #5)

Estos hallazgos requieren cambios en el backend. Ver archivo `referencias/pendientes-backend.md`.

| # | Descripción | Dependencia |
|---|-------------|-------------|
| #4 | Bloques horarios en áreas comunes | Migración DB + controlador |
| #5 | Bloquear reservas al crear mantenimiento | Lógica backend |

---

## Resumen de rutas nuevas

| Ruta | Descripción |
|------|-------------|
| `/admin/providers/list` | Gestión de proveedores (nueva) |

## Archivos creados o modificados

### Nuevos archivos:
- `frontend/src/resources/view/admin/Expenses/providerList.vue`

### Archivos modificados:
- `frontend/src/resources/services/store/provider.store.js`
- `frontend/src/resources/services/store/serviceCategory.store.js`
- `frontend/src/resources/services/store/expense.store.js`
- `frontend/src/resources/services/store/quota.store.js`
- `frontend/src/resources/view/admin/Expenses/expensesList.vue`
- `frontend/src/resources/view/admin/Expenses/expenseForm.vue`
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue`
- `frontend/src/resources/view/admin/financePage.vue`
- `frontend/src/resources/routes/index.js`
