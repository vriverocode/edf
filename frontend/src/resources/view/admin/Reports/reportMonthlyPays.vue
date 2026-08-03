<script setup>
import { onMounted, ref, computed } from 'vue'
import { Notify } from 'quasar'
import { useQuotaStore } from '@/services/store/quota.store'

const quotaStore = useQuotaStore()

const now = new Date()
const year = ref(now.getFullYear())
const loading = ref(false)
const report = ref(null)
const deptFilter = ref(null)
const statusFilter = ref(null)

const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Pagado', value: 3 },
  { label: 'Pendiente', value: 2 },
  { label: 'Vencido', value: 4 },
]

const months = [
  { num: 1, label: 'Ene', full: 'Enero' },
  { num: 2, label: 'Feb', full: 'Febrero' },
  { num: 3, label: 'Mar', full: 'Marzo' },
  { num: 4, label: 'Abr', full: 'Abril' },
  { num: 5, label: 'May', full: 'Mayo' },
  { num: 6, label: 'Jun', full: 'Junio' },
  { num: 7, label: 'Jul', full: 'Julio' },
  { num: 8, label: 'Ago', full: 'Agosto' },
  { num: 9, label: 'Sep', full: 'Septiembre' },
  { num: 10, label: 'Oct', full: 'Octubre' },
  { num: 11, label: 'Nov', full: 'Noviembre' },
  { num: 12, label: 'Dic', full: 'Diciembre' },
]

const allDepartments = computed(() => report.value?.departments || [])
const totals = computed(() => report.value?.totals || {})

// Opciones para el select de filtro
const deptOptions = computed(() => [
  { label: 'Todos', value: null },
  ...allDepartments.value.map((d) => ({ label: d.number.toUpperCase(), value: d.departament_id })),
])

// Departamentos filtrados por unidad y/o status
const departments = computed(() => {
  let list = allDepartments.value
  if (deptFilter.value) {
    list = list.filter((d) => d.departament_id === deptFilter.value)
  }
  if (statusFilter.value !== null) {
    list = list.filter((d) =>
      months.some(({ num }) => d.months[num]?.status === statusFilter.value)
    )
  }
  return list
})

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const cellStyle = (monthData) => {
  if (!monthData) return 'background: #fafafa; color: #bdbdbd;'
  const status = monthData.status
  if (status === 3) return 'background: #e8f5e9; color: #2e7d32; font-weight: 600;'
  if (status === 2) return 'background: #fff8e1; color: #f57f17; font-weight: 600;'
  if (status === 4) return 'background: #ffebee; color: #c62828; font-weight: 600;'
  return 'background: #fff8e1; color: #f57f17;'
}

// Totales globales del footer calculados sobre los departamentos filtrados
const filteredTotals = computed(() => {
  const result = {}
  months.forEach(({ num }) => {
    let amount = 0, paid = 0, pending = 0, overdue = 0
    departments.value.forEach((dept) => {
      const m = dept.months[num]
      if (!m) return
      amount += Number(m.amount) || 0
      if (m.status === 3) paid += Number(m.amount) || 0
      else if (m.status === 4) overdue += Number(m.amount) || 0
      else pending += Number(m.amount) || 0
    })
    result[num] = {
      amount: Math.round(amount * 100) / 100,
      paid: Math.round(paid * 100) / 100,
      pending: Math.round((pending + overdue) * 100) / 100,
    }
  })
  return result
})

const fetchData = async () => {
  loading.value = true
  try {
    const res = await quotaStore.getMonthlyPaymentsReport(year.value)
    if (res?.code === 200) {
      report.value = res.data
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: typeof e === 'string' ? e : 'Error al cargar reporte' })
  } finally {
    loading.value = false
  }
}

const exportCsv = () => {
  const headers = ['Unidad', 'Tipo', 'Responsable', ...months.map((m) => m.full)]
  const rows = departments.value.map((dept) => {
    const monthCells = months.map(({ num }) => {
      const m = dept.months[num]
      return m ? Number(m.amount).toFixed(2) : '0.00'
    })
    return [dept.number, dept.type_label || '', dept.responsible, ...monthCells]
  })

  // Footer rows
  const paidRow = ['COBRADO', '', '', ...months.map(({ num }) => (filteredTotals.value[num]?.paid || 0).toFixed(2))]
  const pendingRow = ['PENDIENTE', '', '', ...months.map(({ num }) => (filteredTotals.value[num]?.pending || 0).toFixed(2))]
  const totalRow = ['TOTAL', '', '', ...months.map(({ num }) => (filteredTotals.value[num]?.amount || 0).toFixed(2))]

  const csv = [headers, ...rows, paidRow, pendingRow, totalRow]
    .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `reporte-cuotas-${year.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>

<template>
  <div class="md:px-36 px-2 pb-10 h-full" style="overflow: auto;">
    <!-- Toolbar -->
    <div class="row q-mb-md items-center q-col-gutter-sm">
      <!-- Año -->
      <div class="col-6 col-md-2">
        <q-input v-model.number="year" type="number" dense borderless class="form__inputsR" label="Año"
          @update:model-value="fetchData" />
      </div>

      <!-- Filtro por departamento -->
      <div class="col-6 col-md-2">
        <q-select
          v-model="deptFilter"
          :options="deptOptions"
          option-label="label"
          option-value="value"
          emit-value
          map-options
          dense
          borderless
          class="form__inputsR"
          label="Unidad"
          clearable
        />
      </div>

      <!-- Filtro por estado -->
      <div class="col-6 col-md-2">
        <q-select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          emit-value
          map-options
          dense
          borderless
          class="form__inputsR"
          label="Estado"
          clearable
        />
      </div>

      <!-- Leyenda -->
      <div class="col row items-center q-gutter-x-md q-gutter-y-xs flex-wrap">
        <div class="flex items-center q-gutter-x-xs">
          <span class="legend-dot" style="background: #e8f5e9; border: 1px solid #2e7d32;"></span>
          <span class="text-caption text-grey-7">Pagado</span>
        </div>
        <div class="flex items-center q-gutter-x-xs">
          <span class="legend-dot" style="background: #fff8e1; border: 1px solid #f57f17;"></span>
          <span class="text-caption text-grey-7">Pendiente</span>
        </div>
        <div class="flex items-center q-gutter-x-xs">
          <span class="legend-dot" style="background: #ffebee; border: 1px solid #c62828;"></span>
          <span class="text-caption text-grey-7">Vencido</span>
        </div>
        <div class="flex items-center q-gutter-x-xs">
          <span class="legend-dot" style="background: #f5f5f5; border: 1px solid #bbb;"></span>
          <span class="text-caption text-grey-7">Sin cuota</span>
        </div>
      </div>

      <!-- Botón exportar -->
      <div class="col-auto">
        <q-btn
          color="green"
          unelevated
          label="Exportar"
          icon="eva-download-outline"
          :disable="departments.length === 0"
          @click="exportCsv"
          size="sm"
        />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>

    <div v-else-if="allDepartments.length === 0" class="text-center text-grey-6 q-py-xl">
      <q-icon name="eva-info-outline" size="4rem" color="grey" />
      <div class="text-h6 q-mt-sm">No hay datos para {{ year }}</div>
    </div>

    <div v-else class="table-wrapper">
      <table class="monthly-pays-table">
        <thead>
          <tr>
            <th class="col-fixed col-dept">Unidad</th>
            <th class="col-fixed col-type">Tipo</th>
            <th class="col-fixed col-responsible">Responsable</th>
            <th v-for="m in months" :key="m.num" class="col-month" :title="m.full">
              {{ m.label }}
            </th>
          </tr>
          <!-- Filas de resumen al inicio (sticky) -->
          <tr class="totals-row totals-row--paid summary-header-row">
            <th class="col-fixed col-dept totals-row--paid">COBRADO</th>
            <th class="col-fixed col-type totals-row--paid"></th>
            <th class="col-fixed col-responsible totals-row--paid"></th>
            <th v-for="m in months" :key="m.num" class="col-month">
              {{ formatMoney(filteredTotals[m.num]?.paid || 0) }}
            </th>
          </tr>
          <tr class="totals-row totals-row--pending summary-header-row">
            <th class="col-fixed col-dept totals-row--pending">PENDIENTE</th>
            <th class="col-fixed col-type totals-row--pending"></th>
            <th class="col-fixed col-responsible totals-row--pending"></th>
            <th v-for="m in months" :key="m.num" class="col-month">
              {{ formatMoney(filteredTotals[m.num]?.pending || 0) }}
            </th>
          </tr>
          <tr class="totals-row totals-row--total summary-header-row">
            <th class="col-fixed col-dept totals-row--total">TOTAL</th>
            <th class="col-fixed col-type totals-row--total"></th>
            <th class="col-fixed col-responsible totals-row--total"></th>
            <th v-for="m in months" :key="m.num" class="col-month">
              {{ formatMoney(filteredTotals[m.num]?.amount || 0) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="dept in departments" :key="dept.departament_id">
            <td class="col-fixed col-dept">{{ dept.number }}</td>
            <td class="col-fixed col-type">{{ dept.type_label || '—' }}</td>
            <td class="col-fixed col-responsible text-ellipsis">{{ dept.responsible }}</td>
            <td v-for="m in months" :key="m.num" class="col-month" :style="cellStyle(dept.months[m.num])">
              <template v-if="dept.months[m.num]">
                {{ formatMoney(dept.months[m.num].amount) }}
              </template>
              <template v-else>—</template>
            </td>
          </tr>
        </tbody>
        <tfoot>
          <!-- Fila: Cobrado por mes -->
          <tr class="totals-row totals-row--paid">
            <td class="col-fixed col-dept text-bold">COBRADO</td>
            <td class="col-fixed col-type"></td>
            <td class="col-fixed col-responsible"></td>
            <td v-for="m in months" :key="m.num" class="col-month text-bold">
              {{ formatMoney(filteredTotals[m.num]?.paid || 0) }}
            </td>
          </tr>
          <!-- Fila: Pendiente por mes -->
          <tr class="totals-row totals-row--pending">
            <td class="col-fixed col-dept text-bold">PENDIENTE</td>
            <td class="col-fixed col-type"></td>
            <td class="col-fixed col-responsible"></td>
            <td v-for="m in months" :key="m.num" class="col-month text-bold">
              {{ formatMoney(filteredTotals[m.num]?.pending || 0) }}
            </td>
          </tr>
          <!-- Fila: Total por mes -->
          <tr class="totals-row totals-row--total">
            <td class="col-fixed col-dept text-bold">TOTAL</td>
            <td class="col-fixed col-type"></td>
            <td class="col-fixed col-responsible"></td>
            <td v-for="m in months" :key="m.num" class="col-month text-bold">
              {{ formatMoney(filteredTotals[m.num]?.amount || 0) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style scoped>
:deep(.form__inputsR .q-field__inner) {
  background: #ffffff;
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}

@media (max-width: 780px) {
  :deep(.form__inputsR .q-field__inner) {
    padding: 0.1rem 1rem;
  }
}

/* Wrapper: mismo look que q-table flat bordered */
.table-wrapper {
  overflow-x: auto;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
}

.monthly-pays-table {
  border-collapse: collapse;
  width: 100%;
  min-width: 900px;
  font-size: 0.8125rem;
  font-family: inherit;
}

.monthly-pays-table th,
.monthly-pays-table td {
  border: 1px solid rgba(0, 0, 0, 0.12);
  padding: 7px 12px;
  text-align: center;
  white-space: nowrap;
}

/* Header: mismo aspecto que q-table thead */
.monthly-pays-table thead th {
  background: #f5f5f5;
  color: rgba(0, 0, 0, 0.54);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 0.7rem;
  position: sticky;
  top: 0;
  z-index: 2;
}

/* Filas de resumen dentro del thead: sticky escalonadas */
.summary-header-row th {
  position: sticky;
  z-index: 2;
  font-weight: 700;
  font-size: 0.75rem;
  border-top: 1px solid rgba(0, 0, 0, 0.12);
}

/* Offset sticky: fila-header = 33px, cada fila de resumen suma ~32px */
.summary-header-row:nth-child(2) th { top: 33px; }
.summary-header-row:nth-child(3) th { top: 65px; }
.summary-header-row:nth-child(4) th { top: 97px; }

/* Colores de las filas de resumen en thead */
.totals-row--paid th   { background: #e8f5e9; color: #2e7d32; }
.totals-row--pending th { background: #fff8e1; color: #f57f17; }
.totals-row--total th  { background: #e3f2fd; color: #1565c0; }

/* Header fijo también sticky en z */
.monthly-pays-table thead .col-fixed {
  z-index: 3;
  background: #f5f5f5;
}

/* col-fixed dentro de filas de resumen del thead: z-index alto + color propio */
.summary-header-row .col-fixed.totals-row--paid    { background: #e8f5e9 !important; z-index: 4; }
.summary-header-row .col-fixed.totals-row--pending { background: #fff8e1 !important; z-index: 4; }
.summary-header-row .col-fixed.totals-row--total   { background: #e3f2fd !important; z-index: 4; }

/* Columnas fijas (sticky left) */
.col-fixed {
  position: sticky;
  z-index: 1;
  background: #ffffff;
  text-align: left;
}

.col-dept {
  left: 0;
  width: 80px;
  min-width: 80px;
  text-transform: uppercase;
}

.col-type {
  left: 80px;
  width: 115px;
  min-width: 115px;
}

.col-responsible {
  left: 195px;
  width: 140px;
  min-width: 140px;
}

.col-month {
  width: 72px;
  min-width: 72px;
  max-width: 72px;
}

/* Hover: mismo tono que q-table */
.monthly-pays-table tbody tr:hover td {
  filter: brightness(0.97);
}

/* Filas de totales del footer */
.totals-row td {
  font-weight: 700;
  border-top: 2px solid rgba(0, 0, 0, 0.12);
}

.totals-row--paid td {
  background: #e8f5e9 !important;
  color: #2e7d32;
}

.totals-row--pending td {
  background: #fff8e1 !important;
  color: #f57f17;
}

.totals-row--total td {
  background: #e3f2fd !important;
  color: #1565c0;
}

/* Sticky de columnas fijas en footer */
.totals-row--paid .col-fixed {
  background: #e8f5e9 !important;
}

.totals-row--pending .col-fixed {
  background: #fff8e1 !important;
} 

.totals-row--total .col-fixed {
  background: #e3f2fd !important;
}

.legend-dot {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  flex-shrink: 0;
}

</style>
<style scoped>
.text-ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 140px;
}
</style>