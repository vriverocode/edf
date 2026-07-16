# Resumen de Avances — Corrección de Bugs y Mejoras UX (Admin)

**Fecha:** 2026-07-16

---

## Módulos Completados

### 06-finanzas-balance-pagos.md — #24, #35, #36, #37, #38, #39, #40 ✅
| # | Cambio |
|---|--------|
| #35 | Ruta `/admin/pays/menu` agregada en router; enlaces corregidos en `payMenu.vue` |
| #24 | `balancesPage.vue`: placeholders reemplazados por enlaces funcionales |
| #36 | Nuevo `Budget/annualBudget.vue` + ruta `/admin/budget/annual` |
| #37 | Nuevo `Reports/reportExpenses.vue` + ruta `/admin/reports/expenses` |
| #38 | Nuevo `Reports/reportDelinquents.vue` + ruta `/admin/reports/delinquents` |
| #39 | Dashboard rediseñado con banner de alertas en `dashboard.vue` |
| #40 | Dashboard con contadores badge en menús + resumen cuotas pendientes |

### 04-usuarios.md — #11, #12, #13, #14, #15, #16 ✅
| # | Cambio |
|---|--------|
| #11 | `usersList.vue`: paginación (`per_page: 20`, `<q-pagination>`) + store refactorizado con `URLSearchParams` |
| #12 | `usersList.vue`: input de búsqueda con debounce |
| #13 | `createUser.vue`: validación paso a paso + marcadores `*` en campos obligatorios |
| #14 | `createUser.vue`: validación de departamento requerido para propietarios |
| #15 | `usersList.vue`: `showDepartment()` extendido a roles 3,4,5 |
| #16 | Nuevas rutas `/admin/users/form/update/:id` y `/admin/pays/user/:id`; nuevos componentes `updateUser.vue` y `userPayments.vue`; `@click` handlers en `usersList.vue` |

### 07-finanzas-presupuesto.md — #25, #26, #27, #28, #29 ✅
| # | Cambio |
|---|--------|
| #25 | `monthlyBillsForm.vue`: máscara corregida a `#.##0,####`; submit usa `parseMaskedMoney()` |
| #26 | `monthlyBillsForm.vue`: muestra consumo del período anterior + validación de anomalía (>50%) |
| #27 | `includeExpensesModal.vue`: selector mes/año bloqueado al período actual |
| #28 | `monthlyBillsForm.vue`: barra de progreso presupuesto vs gastos |
| #29 | `waterReadingsList.vue`: contador de progreso + botón "Registro secuencial" |

### 05-departamentos.md — #17, #18, #19, #20, #21, #22, #23 ✅
| # | Cambio |
|---|--------|
| #17 | "Departamentos" → "Unidades" en menú (`usersPage.vue`) y título de ruta |
| #18 | `departmentList.vue`: nombre del propietario visible en cada tarjeta |
| #19 | `assignPropertyPage.vue`: suma de participación total visible |
| #20 | `departmentList.vue`: buscador en modal de reasignación de propietario |
| #21 | `waterReadingForm.vue`: flujo secuencial (siguiente/anterior, auto-avance al guardar) |
| #22 | `departmentList.vue`: badge de verificación en unidades con lectura registrada |
| #23 | `departmentList.vue` + `updateDepartment.vue`: contexto de página preservado al editar (page + highlight) |

### 01-areas-comunes.md — #1, #2, #3 ✅ (#4, #5 pendientes)
| # | Cambio |
|---|--------|
| #1 | `bookingsList.vue`: paginación, filtros por mes/año, checkbox "Mostrar canceladas", número de depto visible |
| #2 | `bookingsList.vue`: modal de selección de propietario antes de crear reserva (evita reservas flotantes) |
| #3 | `createComunArea.vue` + `updateComunArea.vue`: opciones de tipo de área renombradas para mayor claridad |
| #4 | **PENDIENTE** — Turnos horarios múltiples (requiere cambios en backend: nuevo campo `time_slots` en DB) |
| #5 | **PENDIENTE** — Mantenimiento con hora inicio/fin y bloqueo automático de reservas (requiere lógica backend) |

---

## Totales
- **Hallazgos totales:** 42
- **Completados:** 28
- **Pendientes:** 14
  - #4, #5 (áreas comunes — requieren backend)
  - #6, #7, #8 (reservas — `02-reservas.md`)
  - #9, #10 (noticias/reportes — `03-noticias-reportes.md`)
  - #30 (cuotas — `09-finanzas-cuotas.md`)
  - #31, #32, #33, #34 (gastos — `08-finanzas-gastos.md`)
  - #41, #42 (general — `10-general.md`)

---

## Pendientes por Módulo

### 02-reservas.md — #6, #7, #8
Conciliación y devolución de pagos de reservas. No iniciado.

### 03-noticias-reportes.md — #9, #10
Mejoras en noticias/anuncios y reportes. No iniciado.

### 08-finanzas-gastos.md — #31, #32, #33, #34
Mejoras en gestión de gastos. No iniciado.

### 09-finanzas-cuotas.md — #30
Revisión de cuotas de mantenimiento. No iniciado.

### 10-general.md — #41, #42
Ajustes generales de UX. No iniciado.

---

## Por qué quedaron pendientes #4 y #5

- **#4 (Turnos horarios):** Requiere agregar un campo `time_slots` (JSON array) en la tabla `comun_areas` del backend, además de modificar el endpoint de creación/actualización para aceptar el nuevo formato. El frontend necesita un componente de "bloques horarios" que aún no se implementó.
- **#5 (Mantenimiento con hora):** Requiere que el backend acepte `start_time` y `end_time` en el endpoint de mantenimientos, y que automáticamente cancele/bloquee reservas existentes en ese rango horario. También necesita un endpoint `GET /api/maintenances/by-area/{id}?date=` para consultar mantenimientos programados.
