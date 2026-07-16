# Instrucciones — Finanzas: Balance y Pagos (#24, #35, #36, #37, #38, #39, #40)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/balancesPage.vue`
- `frontend/src/resources/view/admin/Pays/payMenu.vue`
- `frontend/src/resources/view/admin/financePage.vue`
- `frontend/src/resources/routes/index.js`
- `frontend/src/resources/services/store/pay.store.js`
- `frontend/src/resources/services/store/quota.store.js`

---

### #35 — ⚠️ HAY QUE EMPEZAR POR AQUÍ: Finanzas → Pagos da "Página no encontrada"

**Prioridad:** 🔴 Alta
**Tipo:** Bug funcional (crítico)

**Problema:**
La opción "Pagos" en el menú de Finanzas (`financePage.vue`) apunta a `/admin/pays/menu`, pero esa ruta **no existe** en el router → error 404.

**Causa raíz:**
- `financePage.vue` línea ~50: `<div @click="goTo('/admin/pays/menu')">Pagos</div>`
- El archivo del componente existe: `frontend/src/resources/view/admin/Pays/payMenu.vue` (54 líneas, menú con "Pago de cuotas" y "Pago de reservas")
- En `routes/index.js`: **NO hay ruta definida** para `/admin/pays/menu`
- Rutas existentes relacionadas: `/admin/pays/maintenance`, `/admin/pays/booking`, `/admin/pay/validate/:id`, `/admin/pay/register`

**Solución:**

**Paso 1:** Agregar la ruta faltante en `routes/index.js`. Buscar la sección donde están las otras rutas de `admin/pays` (alrededor de la línea 393-404) y agregar:

```js
{
  path: '/admin/pays/menu',
  component: () => import('@/view/admin/Pays/payMenu.vue'),
  name: 'payMenu',
  beforeEnter: [auth, role],
  meta: {
    title: 'PACIFIK',
    pagTitle: 'Pagos',
    roles: ['admin', 'super-admin'],
    depth: 2,
  },
}
```

**Paso 2:** Verificar que `payMenu.vue` funciona correctamente. Revisar sus enlaces:
   - "Pago de cuotas" → `/balances` (¿debería ser `/admin/pays/maintenance`?)
   - "Pago de reservas" → `/admin/accounts` (¿debería ser `/admin/pays/booking`?)
   - **Posiblemente los enlaces de `payMenu.vue` estén mal. Revisar y corregir:**
     - "Pago de cuotas" debería ir a `/admin/pays/maintenance` (listado de pagos de cuotas)
     - "Pago de reservas" debería ir a `/admin/pays/booking` (listado de pagos de reservas)

**Paso 3:** Modificar `payMenu.vue` líneas 14-26:
```js
const menu = [
  {
    title: 'Pago de cuotas',
    icon: pagosCuotas,
    link: '/admin/pays/maintenance',
  },
  {
    title: 'Pago de reservas',
    icon: pagosReservas,
    link: '/admin/pays/booking',
    roles: [1]
  },
]
```

**Archivos a modificar:**
- `frontend/src/resources/routes/index.js` — agregar ruta `/admin/pays/menu`
- `frontend/src/resources/view/admin/Pays/payMenu.vue` — corregir enlaces

**Validación:**
1. Ir a Finanzas → Pagos
2. Verificar que ahora carga `payMenu.vue` en lugar de mostrar 404
3. Hacer clic en "Pago de cuotas" → debe ir a `/admin/pays/maintenance`
4. Hacer clic en "Pago de reservas" → debe ir a `/admin/pays/booking`

---

### #24 — Balance: "Saldos pendientes" y "Gastos comunes" no implementados

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
`balancesPage.vue` tiene dos tarjetas ("Saldos pendientes" y "Gastos comunes") pero ambas muestran "Próximamente disponible" (`placeholder: true`).

**Causa raíz:**
`balancesPage.vue` usa objetos con `placeholder: true` que muestran una notificación en lugar de navegar. No hay implementación real.

**Solución:**

**Paso 1:** Analizar qué datos debe mostrar "Saldos pendientes":
   - Total de cuotas de mantenimiento impagas
   - Lista de propietarios con deuda
   - Montos vencidos

**Paso 2:** Analizar qué datos debe mostrar "Gastos comunes":
   - Total de gastos del período actual
   - Desglose por categoría
   - Comparación con presupuesto

**Paso 3:** Crear componentes/vistas para cada sección o reutilizar existentes:
   - "Saldos pendientes" → podría redirigir a `/admin/quotas/maintenance/list` con filtro "impago"
   - "Gastos comunes" → podría redirigir a `/admin/expenses/list` con filtro del mes actual

**Paso 4:** Como solución mínima viable:
   - Hacer que las tarjetas redirijan a las páginas existentes con filtros pre-aplicados
   - Si se requiere una vista de balance completa, desarrollar nuevo componente

**Archivos a modificar:**
- `frontend/src/resources/view/admin/balancesPage.vue` — reemplazar placeholder con rutas reales
- O crear nuevos componentes de balance

**Validación:**
1. Ir a Finanzas → Balance
2. Hacer clic en "Saldos pendientes" → debe mostrar datos, no "Próximamente"
3. Hacer clic en "Gastos comunes" → debe mostrar datos

---

### #36 — No hay lugar para ingresar el presupuesto anual

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
No existe ninguna opción en el menú para ingresar el presupuesto anual del edificio.

**Causa raíz:**
El sistema actual solo maneja presupuestos mensuales (`/admin/monthly_bills/menu`). No hay modelo de datos ni rutas para presupuesto anual.

**Solución:**

**Paso 1:** Verificar si el backend tiene endpoint para presupuesto anual. Si no, se requiere desarrollo backend.

**Paso 2:** Crear componente `annualBudget.vue` con campos:
   - Año
   - Presupuesto total anual (S/.)
   - Desglose por categorías (opcional)

**Paso 3:** Agregar ruta en `routes/index.js`:
```js
{
  path: '/admin/budget/annual',
  component: () => import('@/view/admin/Budget/annualBudget.vue'),
  name: 'annualBudget',
  beforeEnter: [auth, role],
  meta: { title: 'PACIFIK', pagTitle: 'Presupuesto anual', roles: ['admin'], depth: 2 }
}
```

**Paso 4:** Agregar entrada en `financePage.vue` (menú de finanzas) y/o en `monthlyBillsMenu.vue`.

**Paso 5:** Coordinar con backend la creación del modelo `AnnualBudget` y endpoint `POST/GET /api/annual-budgets`.

**Archivos a modificar:**
- Crear `frontend/src/resources/view/admin/Budget/annualBudget.vue`
- `frontend/src/resources/routes/index.js` — nueva ruta
- `frontend/src/resources/view/admin/financePage.vue` — nuevo botón en menú
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsMenu.vue` — enlace a presupuesto anual

**Validación:**
1. Ir a Finanzas → Presupuesto anual
2. Ingresar un presupuesto para 2026
3. Verificar que se guarda y se puede visualizar

---

### #37 — No hay reportes de gastos

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
No existe un reporte consolidado de gastos que permita al admin ver totales por período, categoría o proveedor.

**Causa raíz:**
No hay componente ni ruta para esto. El store `expense.store.js` tiene `getExpenses(filters)` pero no una función de reporte/resumen.

**Solución:**

**Paso 1:** Agregar acción en `expense.store.js` para reportes consolidados:
```js
getExpensesReport(filters) {
  return ApiService.get(`/api/expenses/report${filterQuery(filters)}`)
}
```

**Paso 2:** Crear componente `reportExpenses.vue` con:
   - Filtros por mes/año, categoría, proveedor
   - Tabla resumen con totales
   - Gráfico de gastos por categoría (opcional con Chart.js o similar)
   - Botón de exportación

**Paso 3:** Agregar ruta y enlace en el menú de finanzas.

**Paso 4:** Coordinar con backend el endpoint `GET /api/expenses/report`.

**Archivos a modificar:**
- Crear `frontend/src/resources/view/admin/Reports/reportExpenses.vue`
- `frontend/src/resources/routes/index.js`
- `frontend/src/resources/view/admin/financePage.vue`
- `frontend/src/resources/services/store/expense.store.js`

**Validación:**
1. Ir a reporte de gastos
2. Filtrar por mes
3. Ver totales, categorías
4. Exportar si aplica

---

### #38 — No hay reporte de morosos

**Prioridad:** 🔴 Alta
**Tipo:** Feature faltante

**Problema:**
No existe un reporte de propietarios con pagos pendientes (morosos).

**Solución:**

**Paso 1:** Crear componente `reportDelinquents.vue`:
   - Lista de propietarios con cuotas impagas
   - Monto adeudado
   - Meses de mora
   - Botón para enviar recordatorio

**Paso 2:** Agregar acción en `quota.store.js` o crear nuevo store:
```js
getDelinquents(filters) {
  return ApiService.get(`/api/quotas/delinquents${filterQuery(filters)}`)
}
```

**Paso 3:** Coordinar con backend endpoint `GET /api/quotas/delinquents`.

**Archivos a modificar:**
- Crear `frontend/src/resources/view/admin/Reports/reportDelinquents.vue`
- `frontend/src/resources/routes/index.js`
- `frontend/src/resources/view/admin/financePage.vue`
- `frontend/src/resources/services/store/quota.store.js`

**Validación:**
1. Ver reporte de morosos
2. Ver propietarios con deuda y montos
3. Poder enviar recordatorio

---

### #39 — No hay alertas de gestión

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
No existen alertas automáticas sobre vencimientos, pagos atrasados o presupuesto excedido.

**Solución:**

**Paso 1:** En el dashboard del admin (`admin/dashboard.vue`), agregar sección de alertas:
   - Cuotas por vencer (próximos 7 días)
   - Pagos atrasados
   - Presupuesto del mes próximo sin registrar
   - Lecturas de agua pendientes

**Paso 2:** Crear store `alert.store.js` o agregar acción en stores existentes.

**Paso 3:** Mostrar badges con cantidad de alertas en el menú principal.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/dashboard.vue` — sección de alertas
- Opcional: crear `frontend/src/resources/services/store/alert.store.js`

**Validación:**
1. Ver dashboard con datos reales
2. Verificar que muestra alertas correctas

---

### #40 — No hay indicadores de gestión (dashboard financiero)

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
No existe un dashboard/resumen financiero que muestre indicadores clave.

**Solución:**

**Paso 1:** En `balancesPage.vue` o como sección separada, agregar indicadores:
   - Total ingresos del mes (pagos recibidos)
   - Total gastos del mes
   - Saldo disponible
   - % de presupuesto ejecutado
   - Cantidad de morosos

**Paso 2:** Crear endpoint backend que devuelva estos indicadores agregados.

**Paso 3:** Mostrar en cards con números grandes y colores semáforo (verde > rojo).

**Archivos a modificar:**
- `frontend/src/resources/view/admin/balancesPage.vue` — dashboard financiero
- O crear `frontend/src/resources/view/admin/Reports/financialDashboard.vue`

**Validación:**
1. Ver dashboard financiero
2. Verificar que los indicadores coinciden con los datos reales

---

**Siguiente archivo:** `07-finanzas-presupuesto.md` — Hallazgos #25, #26, #27, #28, #29
