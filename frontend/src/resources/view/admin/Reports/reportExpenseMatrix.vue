<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { useExpenseStore } from '@/services/store/expense.store'
import moment from 'moment'

const expenseStore = useExpenseStore()

const now = new Date()
const loading = ref(false)
const year = ref(now.getFullYear())
const expenses = ref([])
const totals = ref({ total: 0, paid: 0, pending: 0, count: 0, paid_count: 0, pending_count: 0 })

const providerFilter = ref(null)
const statusFilter = ref(null)

const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Pagado', value: 3 },
  { label: 'Aprobado', value: 2 },
  { label: 'Pendiente', value: 1 },
]

const providers = computed(() => {
  const map = new Map()
  expenses.value.forEach((e) => {
    if (!map.has(e.provider_id)) {
      map.set(e.provider_id, e.provider_name)
    }
  })
  return [{ label: 'Todos', value: null }, ...Array.from(map.entries()).map(([id, name]) => ({ label: name, value: id }))]
})

const filteredExpenses = computed(() => {
  let list = expenses.value
  if (providerFilter.value) {
    list = list.filter((e) => e.provider_id === providerFilter.value)
  }
  if (statusFilter.value !== null) {
    list = list.filter((e) => e.status === statusFilter.value)
  }
  return list
})

const filteredTotal = computed(() => filteredExpenses.value.reduce((s, e) => s + (Number(e.amount) || 0), 0))

const statusClass = (status) => {
  const map = { 1: 'repbok-status--pending', 2: 'repbok-status--approved', 3: 'repbok-status--paid' }
  return map[status] || 'repbok-status--none'
}

const statusDotColor = (status) => {
  const map = { 1: 'warning', 2: 'primary', 3: 'positive' }
  return map[status] || 'grey-5'
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const fetchData = async () => {
  loading.value = true
  try {
    const res = await expenseStore.getExpenseMatrix(year.value)
    if (res?.code === 200) {
      expenses.value = res.data?.expenses || []
      totals.value = res.data?.totals || {}
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: typeof e === 'string' ? e : 'Error al cargar reporte' })
  } finally {
    loading.value = false
  }
}

const exportCsv = () => {
  const headers = ['Proveedor', 'Categoría', 'Mes', 'Factura', 'Descripción', 'Monto', 'Estado', 'Tipo', 'Presupuesto']
  const rows = filteredExpenses.value.map((e) => [
    e.provider_name,
    e.category,
    e.month_label,
    e.invoice_number,
    e.description,
    (Number(e.amount) || 0).toFixed(2),
    e.status_label,
    e.expense_type_label,
    `${e.month_short || ''} ${e.year || ''}`.trim(),
  ])

  const totalRow = ['TOTALES', '', '', '', '', filteredTotal.value.toFixed(2), '', '', '']

  const csv = [headers, ...rows, totalRow]
    .map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `reporte-gastos-${year.value}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>

<template>
  <div class="q-py-md md:px-36 px-2">
    <!-- Toolbar -->
    <div class="row q-mb-md q-col-gutter-sm items-center">
      <div class="col-6 col-md-2">
        <q-input v-model.number="year" type="number" dense borderless class="form__inputsRReportBooking" label="Año" color="primary"
          @update:model-value="fetchData" />
      </div>
      <div class="col-6 col-md-3">
        <q-select v-model="providerFilter" :options="providers" option-label="label" option-value="value"
          emit-value map-options dense borderless class="form__inputsRReportBooking" label="Proveedor" clearable color="primary" />
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value"
          emit-value map-options dense borderless class="form__inputsRReportBooking" label="Estado" clearable color="primary" />
      </div>
      <div class="col-6 col-md-3 row items-center justify-end">
        <q-btn color="green" unelevated label="Exportar" icon="eva-download-outline"
          :disable="filteredExpenses.length === 0" @click="exportCsv" size="sm" />
      </div>
    </div>

    <!-- Stats -->
    <div v-if="!loading" class="row q-mb-md">
      <div class="col-4 px-2 my-1 md:my-0 col-md">
        <q-card flat bordered class="q-pa-sm text-center">
          <div class="text-h5 text-weight-bold text-primary">{{ totals.count }}</div>
          <div class="text-caption text-grey-7">Total gastos</div>
        </q-card>
      </div>
      <div class="col-4 px-2 my-1 md:my-0 col-md">
        <q-card flat bordered class="q-pa-sm text-center">
          <div class="text-h5 text-weight-bold text-positive">{{ totals.paid_count }}</div>
          <div class="text-caption text-grey-7">Pagados</div>
        </q-card>
      </div>
      <div class="col-4 px-2 my-1 md:my-0 col-md">
        <q-card flat bordered class="q-pa-sm text-center">
          <div class="text-h5 text-weight-bold text-warning">{{ totals.pending_count }}</div>
          <div class="text-caption text-grey-7">Pendientes</div>
        </q-card>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredExpenses.length === 0" class="text-center q-pa-lg w-full">
      <q-icon name="eva-inbox-outline" size="48px" color="grey-4" />
      <div class="text-grey-6 q-mt-sm">No se encontraron gastos para {{ year }}</div>
    </div>

    <!-- Table -->
    <div v-else class="repbok-wrapper">
      <div class="repbok-table">
        <!-- Header -->
        <div class="repbok-row repbok-header">
          <div class="repbok-cell repbok-col--provider">Proveedor</div>
          <div class="repbok-cell repbok-col--category">Categoría</div>
          <div class="repbok-cell repbok-col--month">Mes</div>
          <div class="repbok-cell repbok-col--invoice">Factura</div>
          <div class="repbok-cell repbok-col--description">Descripción</div>
          <div class="repbok-cell repbok-col--amount">Monto</div>
          <div class="repbok-cell repbok-col--status">Estado</div>
          <div class="repbok-cell repbok-col--budget">Presupuesto</div>
        </div>

        <!-- Rows -->
        <div class="repbok-row" v-for="expense in filteredExpenses" :key="expense.id">
          <div class="repbok-cell repbok-col--provider" data-title="Proveedor">
            <div class="text-weight-medium">{{ expense.provider_name }}</div>
          </div>
          <div class="repbok-cell repbok-col--category" data-title="Categoría">
            {{ expense.category }}
          </div>
          <div class="repbok-cell repbok-col--month" data-title="Mes">
            {{ expense.month_label }}
          </div>
          <div class="repbok-cell repbok-col--invoice" data-title="Factura">
            {{ expense.invoice_number }}
          </div>
          <div class="repbok-cell repbok-col--description" data-title="Descripción">
            <div class="text-ellipsis">{{ expense.description }}</div>
          </div>
          <div class="repbok-cell repbok-col--amount text-right" data-title="Monto">
            <span class="text-weight-bold">{{ formatMoney(expense.amount) }}</span>
          </div>
          <div class="repbok-cell repbok-col--status" data-title="Estado">
            <q-badge :color="statusDotColor(expense.status)" class="q-px-sm q-py-xs">
              {{ expense.status_label }}
            </q-badge>
          </div>
          <div class="repbok-cell repbok-col--budget" data-title="Presupuesto">
            {{ expense.month_short ? `${expense.month_short} ${expense.year}` : '—' }}
          </div>
        </div>

        <!-- Footer -->
        <div class="repbok-row repbok-footer">
          <div class="repbok-cell repbok-col--provider text-bold">TOTALES</div>
          <div class="repbok-cell repbok-col--category"></div>
          <div class="repbok-cell repbok-col--month"></div>
          <div class="repbok-cell repbok-col--invoice"></div>
          <div class="repbok-cell repbok-col--description"></div>
          <div class="repbok-cell repbok-col--amount text-right text-bold">{{ formatMoney(filteredTotal) }}</div>
          <div class="repbok-cell repbok-col--status"></div>
          <div class="repbok-cell repbok-col--budget"></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.form__inputsRReportBooking .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>

<style scoped lang="scss">
.repbok-wrapper {
  margin: 0 auto;
  width: 100%;
  overflow-x: auto;
}

.repbok-table {
  margin: 0 0 40px 0;
  width: 100%;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  display: table;
}

@media screen and (max-width: 580px) {
  .repbok-table {
    display: block;
  }
}

.repbok-row {
  display: table-row !important;
  background: #f6f6f6;
  margin: 0;
}

.repbok-row:nth-of-type(odd) {
  background: #e9e9e9;
}

.repbok-row.repbok-header {
  font-weight: 900;
  color: #ffffff;
  background: $primary;
}

.repbok-row.repbok-footer {
  font-weight: 700;
  background: #e3f2fd !important;
  color: #1565c0;
}

@media screen and (max-width: 580px) {
  .repbok-row {
    padding: 14px 0 7px;
    display: block !important;
  }

  .repbok-row.repbok-header {
    padding: 0;
    height: 6px;
  }

  .repbok-row.repbok-header .repbok-cell {
    display: none;
  }

  .repbok-row .repbok-cell {
    margin-bottom: 10px;
  }

  .repbok-row .repbok-cell:before {
    margin-bottom: 3px;
    content: attr(data-title);
    min-width: 98px;
    font-size: 10px;
    line-height: 10px;
    font-weight: bold;
    text-transform: uppercase;
    color: #969696;
    display: block;
  }
}

.repbok-cell {
  padding: 6px 12px;
  display: table-cell;
  vertical-align: middle;
  font-size: 0.8125rem;
}

@media screen and (max-width: 580px) {
  .repbok-cell {
    padding: 2px 16px;
    display: block;
  }
}

/* Column widths */
.repbok-col--provider { min-width: 160px; }
.repbok-col--category { min-width: 100px; }
.repbok-col--month { min-width: 80px; }
.repbok-col--invoice { min-width: 100px; }
.repbok-col--description { min-width: 140px; }
.repbok-col--amount { min-width: 100px; text-align: right; }
.repbok-col--status { min-width: 110px; text-align: center; }
.repbok-col--budget { min-width: 100px; }

/* Status badges */
.repbok-status {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 0.75rem;
}

.repbok-status--paid {
  background: #e8f5e9;
  color: #2e7d32;
}

.repbok-status--pending {
  background: #fff8e1;
  color: #f57f17;
}

.repbok-status--approved {
  background: #e3f2fd;
  color: #1565c0;
}

.repbok-status--none {
  background: #f5f5f5;
  color: #9e9e9e;
}

/* Text ellipsis for description */
.text-ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 180px;
}
</style>
