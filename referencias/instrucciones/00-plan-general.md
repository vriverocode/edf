# Plan General — Corrección de Bugs y Mejoras de Usabilidad (Admin)

**Basado en:** `referencias/pacifik-bugs-usabilidad-admin.md`
**Fecha:** 2026-07-16
**Alcance:** Frontend Vue 3 (rol admin)

---

## Estructura de archivos

```
referencias/instrucciones/
├── 00-plan-general.md           ← este archivo
├── 01-areas-comunes.md          #1, #2, #3, #4, #5
├── 02-reservas.md               #6, #7, #8
├── 03-noticias-reportes.md      #9, #10
├── 04-usuarios.md               #11, #12, #13, #14, #15, #16
├── 05-departamentos.md          #17, #18, #19, #20, #21, #22, #23
├── 06-finanzas-balance-pagos.md #24, #35, #36, #37, #38, #39, #40
├── 07-finanzas-presupuesto.md   #25, #26, #27, #28, #29
├── 08-finanzas-gastos.md        #31, #32, #33, #34
├── 09-finanzas-cuotas.md        #30
└── 10-general.md                #41, #42
```

## Orden de implementación sugerido

### Fase 1 — Bugs críticos (arreglar primero)
| Orden | # | Archivo de instrucciones |
|-------|---|--------------------------|
| 1 | #35 | `06-finanzas-balance-pagos.md` — Pagos da 404 |
| 2 | #16 | `04-usuarios.md` — Editar usuario y Ver pagos no funcionan |
| 3 | #25 | `07-finanzas-presupuesto.md` — Campo agua se rompe al 4° dígito |
| 4 | #14 | `04-usuarios.md` — Propietarios sin unidad quedan inubicables |
| 5 | #2  | `01-areas-comunes.md` — Reserva admin sin departamento |
| 6 | #19 | `05-departamentos.md` — % participación no suma unidades |

### Fase 2 — Huecos de proceso financiero
| Orden | # | Archivo de instrucciones |
|-------|---|--------------------------|
| 7 | #6, #7 | `02-reservas.md` — Conciliación y devolución de pagos |
| 8 | #27 | `07-finanzas-presupuesto.md` — Mezcla de gastos entre periodos |
| 9 | #36, #37, #38, #39, #40 | `06-finanzas-balance-pagos.md` — Features financieros faltantes |
| 10 | #24 | `06-finanzas-balance-pagos.md` — Balance placeholders |

### Fase 3 — Impráctico a escala (~180 unidades)
| Orden | # | Archivo de instrucciones |
|-------|---|--------------------------|
| 11 | #21, #22, #29 | `05-departamentos.md` + `07-finanzas-presupuesto.md` — Agua |
| 12 | #11, #12, #20 | `04-usuarios.md` — Listados sin paginar/buscar |
| 13 | #1, #8, #10 | `01-areas-comunes.md` + `02-reservas.md` — Reservas/reportes |

### Fase 4 — Mejoras UX (agrupar)
| Orden | # | Archivo de instrucciones |
|-------|---|--------------------------|
| 14 | #3, #4, #5 | `01-areas-comunes.md` |
| 15 | #9 | `03-noticias-reportes.md` |
| 16 | #15, #17, #18, #23 | `04-usuarios.md` + `05-departamentos.md` |
| 17 | #26, #28 | `07-finanzas-presupuesto.md` |
| 18 | #31, #32, #33, #34 | `08-finanzas-gastos.md` |
| 19 | #41 | `10-general.md` |
| 20 | #30 | `09-finanzas-cuotas.md` |

### Fase 5 — Pendientes de validar
| # | Descripción |
|---|-------------|
| 19 | Confirmar con negocio regla de % de participación |
| 30 | Revisar Cuotas de mantenimiento cuando haya data real |
| 5 | Validar prioridad de mantenimiento programado con negocio |
| 42 | Rediseño transversal de flujo de finanzas |

---

## Convenciones para todos los archivos

Cada archivo de instrucciones sigue esta estructura por hallazgo:

```markdown
### #N — Título del hallazgo

**Prioridad:** 🔴 Alta | 🟠 Media | 🟡 Baja
**Tipo:** Bug funcional | Feature faltante | Mejora UX

**Problema:**
(qué ocurre actualmente)

**Causa raíz:**
(por qué ocurre — referencias a código con líneas)

**Solución:**
(pasos concretos para resolver)

**Archivos a modificar:**
- `ruta/al/archivo.vue` — qué cambiar
- `ruta/al/store.js` — qué cambiar
- `ruta/al/router.js` — qué cambiar

**Validación:**
(cómo probar que está corregido)
```

---

## Mapa de rutas del frontend (referencia rápida)

### Layout principal: `panelLayout` → guard `auth`
### Layout auth: `authLayout` → guard `guest`

#### Rutas admin principales:

| Ruta | Componente | Módulo |
|------|-----------|--------|
| `/admin/comun-area/list` | `ComunAreas/comunAreasList.vue` | Áreas comunes |
| `/admin/comun-area/bookings/:id/list` | `ComunAreas/bookingsList.vue` | Reservas por área |
| `/admin/comun-area/maintenance/:id/create` | `ComunAreas/createMaintenance.vue` | Mantenimiento |
| `/reserves` | `admin/reservesPage.vue` | Reservas (admin) |
| `/admin/notices` | `Notices/noticesPage.vue` | Noticias |
| `/admin/reports` | `Others/reportsMenu.vue` | Reportes |
| `/admin/reports/bookings` | `Reports/reportBookings.vue` | Reporte reservas |
| `/admin/users/list` | `Users/usersList.vue` | Usuarios |
| `/admin/users/form/add` | `Users/createUser.vue` | Crear usuario |
| `/admin/users/assign-property/:id` | `Users/assignPropertyPage.vue` | Asignar unidad |
| `/admin/department/list` | `Department/departmentList.vue` | Unidades |
| `/admin/department/form/add` | `Department/createUnit.vue` | Crear unidad |
| `/admin/apartments/edit/:id` | `Department/updateDepartment.vue` | Editar unidad |
| `/balances` | `admin/balancesPage.vue` | Balance |
| `/admin/finance` | `admin/financePage.vue` | Menú finanzas |
| `/admin/monthly_bills/menu` | `MonthlyBills/monthlyBillsMenu.vue` | Presupuesto menú |
| `/admin/monthly_bills/form/add` | `MonthlyBills/monthlyBillsForm.vue` | Crear presupuesto |
| `/admin/water_readings/list` | `WaterReadings/waterReadingsList.vue` | Medición agua |
| `/admin/water_readings/form/add` | `WaterReadings/waterReadingForm.vue` | Registrar agua |
| `/admin/expenses/list` | `Expenses/expensesList.vue` | Gastos |
| `/admin/expenses/form/add` | `Expenses/expenseForm.vue` | Crear gasto |
| `/admin/quotas/pays` | `Quotas/quotasMenu.vue` | Cuotas menú |
| `/admin/pays/maintenance` | `Pays/payMaintenanceList.vue` | Pagos cuotas |
| `/admin/pays/booking` | `Pays/payBookingList.vue` | Pagos reservas |
| `/admin/pay/validate/:id` | `Pays/validatePay.vue` | Validar pago |
| **`/admin/pays/menu`** | **`Pays/payMenu.vue`** | **❌ Ruta faltante → 404 (#35)** |

#### Stores principales:

| Store | Archivo | Propósito |
|-------|---------|-----------|
| `useUserStore` | `users.store.js` | Usuarios |
| `useApartmentStore` | `apartment.store.js` | Unidades |
| `useComunAreaStore` | `comunArea.store.js` | Áreas comunes |
| `useReserveStore` | `reserve.store.js` | Reservas |
| `usePayStore` | `pay.store.js` | Pagos |
| `useQuotaStore` | `quota.store.js` | Cuotas |
| `useMonthlyBillsStore` | `monthlyBills.store.js` | Presupuestos |
| `useExpenseStore` | `expense.store.js` | Gastos |
| `useWaterReadingsStore` | `waterReadings.store.js` | Lecturas agua |
| `useNoticeStore` | `notice.store.js` | Noticias |
| `useMaintenanceStore` | `maintenance.store.js` | Mantenimiento |
| `useProviderStore` | `provider.store.js` | Proveedores |
| `useServiceCategoryStore` | `serviceCategory.store.js` | Categorías servicio |
| `useReportStore` | `report.store.js` | Reportes |

---

**Siguiente archivo:** `01-areas-comunes.md` — Hallazgos #1, #2, #3, #4, #5
