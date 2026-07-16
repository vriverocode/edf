# Instrucciones — Finanzas: Presupuesto (#25, #26, #27, #28, #29)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue`
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsList.vue`
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue`
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsEditForm.vue`
- `frontend/src/resources/view/admin/WaterReadings/waterReadingsList.vue`
- `frontend/src/resources/view/admin/WaterReadings/waterReadingForm.vue`
- `frontend/src/resources/components/monthlyBills/includeExpensesModal.vue`
- `frontend/src/resources/services/store/monthlyBills.store.js`
- `frontend/src/resources/services/store/waterReadings.store.js`

---

### #25 — Campo "Consumo total de agua (m³)" se rompe al 4° dígito

**Prioridad:** 🔴 Alta
**Tipo:** Bug funcional

**Problema:**
En `monthlyBillsForm.vue`, el campo `total_water_consumption_m3` solo acepta 3 dígitos. Al ingresar el 4°, el campo se reinicia y borra lo escrito.

**Causa raíz:**
`monthlyBillsForm.vue` línea 205:
```html
<q-input ... mask="###.###.###,####" ... v-model.number="formData.total_water_consumption_m3" />
```
La máscara `###.###.###,####` es para valores con formato de miles y decimales. El problema es que la máscara usa `###.###.###,####` que permite hasta 3 dígitos enteros antes del primer punto. Con `v-model.number`, al escribir el 4° dígito, el valor pierde el formato de máscara y Quasar lo reinicia.

**Solución:**

**Paso 1:** Cambiar la máscara a una que soporte más dígitos enteros:
```html
<q-input ... mask="###.###.###.###,####" ... v-model="formData.total_water_consumption_m3" />
```
O mejor, usar una máscara genérica sin límite de enteros:
```html
<q-input ... mask="#.##0,####" reverse-fill-mask ... v-model="formData.total_water_consumption_m3" />
```

**Paso 2:** Cambiar `v-model.number` a `v-model` (sin `.number`) para evitar conflictos con la máscara. La conversión a número se hace en `parseMaskedMoney` al enviar.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue` — línea 205-206, cambiar máscara y v-model

**Validación:**
1. Crear presupuesto mensual
2. En "Consumo total de agua (m³)", ingresar un valor de 4 dígitos (ej: 1234)
3. Verificar que el campo acepta el valor sin reiniciarse
4. Enviar formulario y verificar que se guarda correctamente

---

### #26 — No se muestra ni se valida el consumo del periodo anterior

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX / Control

**Problema:**
Al registrar el consumo de agua del período actual, no hay referencia al consumo del período anterior ni validación de anomalías.

**Causa raíz:**
`monthlyBillsForm.vue` no consulta el presupuesto del mes anterior para mostrar el consumo previo.

**Solución:**

**Paso 1:** Al cargar `monthlyBillsForm.vue`, consultar el presupuesto del mes anterior:
```js
const previousMonth = computed(() => {
  const m = formData.value.month?.value - 1
  const y = m === 0 ? formData.value.year - 1 : formData.value.year
  return { month: m === 0 ? 12 : m, year: y }
})

const loadPreviousMonthData = async () => {
  try {
    const response = await monthlyBillsStore.getMonthlyBills({
      month: previousMonth.value.month,
      year: previousMonth.value.year
    })
    if (response?.code === 200 && response.data?.data?.length > 0) {
      previousBill.value = response.data.data[0]
    }
  } catch (e) { /* ignore */ }
}
```

**Paso 2:** Mostrar el consumo anterior en el formulario:
```html
<div v-if="previousBill" class="text-caption text-grey-6">
  Consumo del período anterior: {{ previousBill.total_water_consumption_m3 }} m³
  ({{ previousBill.month }}/{{ previousBill.year }})
</div>
```

**Paso 3:** Agregar validación al enviar: si el nuevo consumo difiere del anterior en más del 50%, mostrar confirmación:
```js
if (previousBill && previousBill.total_water_consumption_m3) {
  const prevConsumption = Number(previousBill.total_water_consumption_m3)
  const newConsumption = Number(formData.value.total_water_consumption_m3)
  if (prevConsumption > 0 && newConsumption > 0) {
    const diff = Math.abs(newConsumption - prevConsumption) / prevConsumption
    if (diff > 0.5) {
      // Mostrar confirmación al admin
      if (!confirm(`El consumo actual (${newConsumption} m³) difiere en más del 50% del período anterior (${prevConsumption} m³). ¿Deseas continuar?`)) {
        return
      }
    }
  }
}
```

**Archivos a modificar:**
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue` — cargar mes anterior, mostrar y validar

**Validación:**
1. Crear presupuesto para un mes
2. Crear presupuesto para el mes siguiente
3. Verificar que muestra el consumo del mes anterior como referencia
4. Ingresar un consumo muy distinto y verificar la alerta

---

### #27 — "Incluir gastos" permite mezclar periodos

**Prioridad:** 🟠 Media
**Tipo:** Regla de negocio

**Problema:**
El botón "Incluir gastos" en `monthlyBillsForm.vue` permite seleccionar gastos de periodos pasados y agregarlos al periodo actual, distorsionando el presupuesto.

**Causa raíz:**
`includeExpensesModal.vue` probablemente llama `expenseStore.getUnassignedExpenses(month, year)` sin filtrar por el mes/año del presupuesto actual, o el modal permite seleccionar gastos de cualquier período.

**Solución:**

**Paso 1:** En `monthlyBillsForm.vue`, al abrir `includeExpensesModal`, pasar el mes y año del presupuesto actual:
```html
<includeExpensesModal
  :dialog="showExpensesModal"
  :current-month="formData.month?.value"
  :current-year="formData.year"
  ...
/>
```

**Paso 2:** En `includeExpensesModal.vue`, asegurarse de que solo muestra gastos del mismo mes/año que el presupuesto, o gastos sin asignar que no superen el año anterior.

**Paso 3:** Agregar un filtro visual en el modal que muestre la fecha del gasto para que el admin pueda identificar si pertenece al período correcto.

**Archivos a modificar:**
- `frontend/src/resources/components/monthlyBills/includeExpensesModal.vue` — filtrar por período
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue` — pasar parámetros correctos

**Validación:**
1. Abrir presupuesto de enero 2026
2. Hacer clic en "Incluir gastos"
3. Verificar que solo aparecen gastos del periodo enero 2026 (o meses anteriores razonables)
4. No permitir seleccionar gastos de periodos muy anteriores sin advertencia

---

### #28 — No hay indicador de avance del presupuesto

**Prioridad:** 🟠 Media
**Tipo:** Feature faltante

**Problema:**
No existe un indicador que muestre cuánto se ha gastado del presupuesto total en el período.

**Causa raíz:**
`monthlyBillsForm.vue`, `monthlyBillsList.vue` y `monthlyBillsDetails.vue` no muestran progreso.

**Solución:**

**Paso 1:** En `monthlyBillsForm.vue` y `monthlyBillsEditForm.vue`, después de cargar los gastos incluidos, calcular y mostrar una barra de progreso:
```html
<div class="q-mt-md">
  <div class="text-subtitle2 text-black">Avance del presupuesto</div>
  <q-linear-progress :value="progressValue" color="primary" class="q-mt-sm" size="20px">
    <div class="absolute-full flex flex-center text-white text-bold">
      {{ progressPercent }}%
    </div>
  </q-linear-progress>
  <div class="text-caption text-grey-6 q-mt-xs">
    S/. {{ spentAmount }} de S/. {{ totalBudget }} ({{ progressPercent }}%)
  </div>
</div>
```

**Paso 2:** Calcular el progreso:
```js
const totalBudget = computed(() => parseMaskedMoney(formData.value.total_maintenance_budget) || 0)
const totalExpenses = computed(() => previousSelectedTotal.value) // total de gastos incluidos

const progressPercent = computed(() => {
  if (totalBudget.value === 0) return 0
  return Math.min(100, Math.round((totalExpenses.value / totalBudget.value) * 100))
})
```

**Paso 3:** En `monthlyBillsDetails.vue`, también mostrar la barra de progreso al cargar los datos del presupuesto.

**Archivos a modificar:**
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsForm.vue` — barra de progreso
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsEditForm.vue` — barra de progreso
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue` — barra de progreso
- `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsList.vue` — indicador en cada tarjeta

**Validación:**
1. Crear presupuesto con presupuesto total y gastos incluidos
2. Verificar que la barra de progreso muestra la relación correcta
3. Editar y verificar que se actualiza

---

### #29 — Medición de agua: misma lista larga sin indicador

**Prioridad:** 🔴 Alta
**Tipo:** Mejora UX

**Problema:**
`waterReadingsList.vue` y `waterReadingForm.vue` tienen el mismo problema que `departmentList.vue`: listas largas sin indicador de qué unidades ya tienen lectura registrada y sin flujo secuencial.

**Causa raíz:**
Ver #21 y #22 — es el mismo problema desde la vista de medición de agua.

**Solución:**

**Paso 1:** En `waterReadingsList.vue`, mejorar la tabla para que:
   - Agregar columna "Estado" con icono verde si ya se registró lectura en el período, gris si no
   - Agregar filtro "Solo pendientes" para ver solo unidades sin lectura
   - Agregar búsqueda por número de departamento

**Paso 2:** En `waterReadingsList.vue`, agregar botón "Comenzar registro secuencial" que inicie el flujo del #21 (waterReadingForm con auto-avance).

**Paso 3:** Agregar un contador de progreso: "12/180 unidades registradas".

**Archivos a modificar:**
- `frontend/src/resources/view/admin/WaterReadings/waterReadingsList.vue` — columna estado, filtro, contador
- `frontend/src/resources/services/store/waterReadings.store.js` — acción para obtener estado por unidad (opcional)

**Validación:**
1. Ir a Medición de agua
2. Ver el listado con indicadores de qué unidades ya tienen lectura
3. Usar filtro "Solo pendientes" para ver solo las que faltan
4. Ver el contador de progreso

---

**Siguiente archivo:** `08-finanzas-gastos.md` — Hallazgos #31, #32, #33, #34
