<script setup>
import { ref, computed, onMounted } from 'vue'
import { useReportStore } from '@/services/store/report.store'
import { useQuasar } from 'quasar'
import moment from 'moment'
import { useRoute, useRouter } from 'vue-router'

const $q = useQuasar()
const reportStore = useReportStore()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const exporting = ref(false)
const rows = ref([])
const metrics = ref({})
const pagination = ref({ page: 1, rowsNumber: 0, rowsPerPage: 25 })
const search = ref('')
const searchTimeout = ref(null)
const sortBy = ref('pay_date')
const sortDir = ref('desc')
const sortableColumns = { pay_date: 'Fecha', dept_number: 'Depto.', month: 'Cuota', amount: 'Monto', status: 'Estado' }

const now = new Date()
const filters = ref({
  status: -1,
  date_from: null,
  date_to: null,
})

const statusOptions = [
  { label: 'Todos', value: -1 },
  { label: 'Cancelado', value: 0 },
  { label: 'Pendiente de aprobación', value: 1 },
  { label: 'Exitoso', value: 2 },
  { label: 'Rechazado', value: 3 },
  { label: 'Reembolsado parcialmente', value: 4 },
  { label: 'Reembolsado', value: 5 },
]

const stats = computed(() => [
  { label: 'Total pagado', value: `S/. ${Number(metrics.value.total_amount || 0).toFixed(2)}`, color: 'text-primary', icon: 'eva-credit-card-outline' },
  { label: 'Total pagos', value: metrics.value.total_count || 0, color: 'text-grey-9', icon: 'eva-list-outline' },
  { label: 'Exitosos', value: metrics.value.approved_count || 0, color: 'text-positive', icon: 'eva-checkmark-circle-outline' },
  { label: 'Pendientes', value: metrics.value.pending_count || 0, color: 'text-warning', icon: 'eva-alert-circle-outline' },
  { label: 'Rechazados', value: metrics.value.rejected_count || 0, color: 'text-negative', icon: 'eva-close-circle-outline' },
])

const totalPages = computed(() => {
  const total = Number(pagination.value.rowsNumber) || 0
  const perPage = Number(pagination.value.rowsPerPage) || 25
  return Math.max(1, Math.ceil(total / perPage))
})

function setToday() {
  const today = moment().format('DD/MM/YYYY')
  filters.value.date_from = today
  filters.value.date_to = today
  onFilterChange()
}

function setThisMonth() {
  filters.value.date_from = moment().startOf('month').format('DD/MM/YYYY')
  filters.value.date_to = moment().endOf('month').format('DD/MM/YYYY')
  onFilterChange()
}

function clearDates() {
  filters.value.date_from = null
  filters.value.date_to = null
  onFilterChange()
}

function onFilterChange() {
  pagination.value.page = 1
  syncToUrl()
  loadData()
}

function toggleSort(column) {
  if (!(column in sortableColumns)) return
  if (sortBy.value === column) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortBy.value = column
    sortDir.value = 'desc'
  }
  pagination.value.page = 1
  loadData()
}

function onSearchChange() {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    pagination.value.page = 1
    syncToUrl()
    loadData()
  }, 400)
}

function onPageChange(newPage) {
  pagination.value.page = newPage
  syncToUrl()
  loadData()
}

function syncToUrl() {
  const query = { ...route.query }
  const set = (k, v) => {
    if (v === '' || v === null || v === undefined) delete query[k]
    else query[k] = v
  }
  set('search', search.value)
  set('status', filters.value.status !== -1 ? filters.value.status : null)
  set('date_from', filters.value.date_from)
  set('date_to', filters.value.date_to)
  set('page', pagination.value.page > 1 ? pagination.value.page : null)
  set('sort_by', sortBy.value !== 'pay_date' ? sortBy.value : null)
  set('sort_dir', sortDir.value !== 'desc' ? sortDir.value : null)
  router.replace({ query })
}

function restoreFromQuery() {
  if (route.query.search) search.value = route.query.search
  if (route.query.status !== undefined) filters.value.status = Number(route.query.status)
  if (route.query.date_from) filters.value.date_from = route.query.date_from
  if (route.query.date_to) filters.value.date_to = route.query.date_to
  if (route.query.page) pagination.value.page = Number(route.query.page)
  if (route.query.sort_by) sortBy.value = route.query.sort_by
  if (route.query.sort_dir) sortDir.value = route.query.sort_dir
}

async function loadData() {
  loading.value = true
  try {
    const params = {
      search: search.value || null,
      status: filters.value.status,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      per_page: pagination.value.rowsPerPage,
      page: pagination.value.page,
    }
    const res = await reportStore.getPaymentsReport(params)
    const data = res.data
    rows.value = data.data
    metrics.value = data.metrics
    pagination.value.page = data.meta.current_page
    pagination.value.rowsNumber = data.meta.total
    pagination.value.rowsPerPage = data.meta.per_page
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al cargar pagos' })
  } finally {
    loading.value = false
  }
}

async function handleExport() {
  exporting.value = true
  try {
    const params = {
      search: search.value || null,
      status: filters.value.status,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    }
    await reportStore.exportPaymentsReport(params)
    $q.notify({ type: 'positive', message: 'Reporte exportado exitosamente' })
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al exportar' })
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  restoreFromQuery()
  loadData()
})
</script>

<template>
  <div class="q-py-md md:px-36 px-2">
    <!-- Barra de filtros -->
    <div class="row q-mb-sm q-col-gutter-sm items-center">
      <!-- Búsqueda -->
      <div class="col-12 col-md-4">
        <q-input v-model="search" dense borderless class="form__inputsReport" label="Buscar por nombre o departamento..."
          clearable color="primary" @update:model-value="onSearchChange">
          <template #prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>

      <!-- Estado -->
      <div class="col-8 col-md-2">
        <q-select v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
          emit-value map-options dense borderless class="form__inputsReport" label="Estado" clearable color="primary"
          @update:model-value="onFilterChange" />
      </div>

      <!-- Botones acción -->
      <div class="col-4 flex md:justify-start justify-end pr-1">
        <q-btn color="green" unelevated no-caps icon="eva-download-outline" label="Exportar" size="sm"
          :loading="exporting" @click="handleExport" :disable="rows.length === 0" />
      </div>
    </div>

    <!-- Filtros rápidos + Rango de fechas -->
    <div class="row q-mb-md q-col-gutter-sm items-center">
      <div  class="col-12 md:pt-5 pt-4">
        Buscar por fecha
      </div>
      <div class="col-6 col-md-2">
        <q-input dense outlined v-model="filters.date_from" label="Desde" mask="##/##/####" color="primary"
          @update:model-value="onFilterChange" clearable @clear="onFilterChange">
          <template v-slot:append>
            <q-icon name="eva-calendar-outline" class="cursor-pointer">
              <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                <q-date mask="DD/MM/YYYY" v-model="filters.date_from" @update:model-value="onFilterChange" />
              </q-popup-proxy>
            </q-icon>
          </template>
        </q-input>
      </div>
      <div class="col-6 col-md-2">
        <q-input dense outlined v-model="filters.date_to" label="Hasta" mask="##/##/####" color="primary"
          @update:model-value="onFilterChange" clearable @clear="onFilterChange">
          <template v-slot:append>
            <q-icon name="eva-calendar-outline" class="cursor-pointer">
              <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                <q-date mask="DD/MM/YYYY" v-model="filters.date_to" @update:model-value="onFilterChange" />
              </q-popup-proxy>
            </q-icon>
          </template>
        </q-input>
      </div>
      <div class="col-12 col-md-4 row md:justify-start justify-center q-gutter-xs">
        <q-btn outline color="primary" size="sm" icon="eva-calendar-outline" label="Hoy" no-caps @click="setToday" />
        <q-btn outline color="primary" size="sm" icon="eva-calendar-outline" label="Este mes" no-caps @click="setThisMonth" />
        <q-btn color="grey-7" size="sm" icon="eva-close-outline" label="Limpiar fechas" outline no-caps @click="clearDates"
          v-if="filters.date_from || filters.date_to" />
      </div>
    </div>

    <!-- Métricas -->
    <div class="row q-col-gutter-sm q-mb-lg">
      <div v-for="(stat, i) in stats" :key="i" class="col-6 col-md">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 q-pa-sm text-center">
          <div :class="['text-h6 font-bold', stat.color]">{{ stat.value }}</div>
          <div class="text-caption text-grey-6">{{ stat.label }}</div>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="repbok-wrapper">
      <q-inner-loading :showing="loading" color="primary" />
      <div v-if="rows.length > 0" class="repbok-table">
        <!-- Header -->
        <div class="repbok-row repbok-header">
          <div class="repbok-cell" data-title="Fecha"
            :class="{ 'sortable': 'pay_date' in sortableColumns, 'active-sort': sortBy === 'pay_date' }"
            @click="toggleSort('pay_date')">
            Fecha
            <q-icon v-if="sortBy === 'pay_date'" :name="sortDir === 'desc' ? 'eva-arrow-downward-outline' : 'eva-arrow-upward-outline'" size="14px" class="q-ml-xs" />
          </div>
          <div class="repbok-cell" data-title="Usuario">Usuario</div>
          <div class="repbok-cell" data-title="Depto."
            :class="{ 'sortable': 'dept_number' in sortableColumns, 'active-sort': sortBy === 'dept_number' }"
            @click="toggleSort('dept_number')">
            Depto.
            <q-icon v-if="sortBy === 'dept_number'" :name="sortDir === 'desc' ? 'eva-arrow-downward-outline' : 'eva-arrow-upward-outline'" size="14px" class="q-ml-xs" />
          </div>
          <div class="repbok-cell" data-title="Cuota"
            :class="{ 'sortable': 'month' in sortableColumns, 'active-sort': sortBy === 'month' }"
            @click="toggleSort('month')">
            Cuota
            <q-icon v-if="sortBy === 'month'" :name="sortDir === 'desc' ? 'eva-arrow-downward-outline' : 'eva-arrow-upward-outline'" size="14px" class="q-ml-xs" />
          </div>
          <div class="repbok-cell" data-title="Monto"
            :class="{ 'sortable': 'amount' in sortableColumns, 'active-sort': sortBy === 'amount' }"
            @click="toggleSort('amount')">
            Monto
            <q-icon v-if="sortBy === 'amount'" :name="sortDir === 'desc' ? 'eva-arrow-downward-outline' : 'eva-arrow-upward-outline'" size="14px" class="q-ml-xs" />
          </div>
          <div class="repbok-cell" data-title="Método">Método</div>
          <div class="repbok-cell" data-title="Referencia">Referencia</div>
          <div class="repbok-cell" data-title="Estado"
            :class="{ 'sortable': 'status' in sortableColumns, 'active-sort': sortBy === 'status' }"
            @click="toggleSort('status')">
            Estado
            <q-icon v-if="sortBy === 'status'" :name="sortDir === 'desc' ? 'eva-arrow-downward-outline' : 'eva-arrow-upward-outline'" size="14px" class="q-ml-xs" />
          </div>
        </div>
        <!-- Rows -->
        <div v-for="row in rows" :key="row.id" class="repbok-row">
          <div class="repbok-cell" data-title="Fecha">
            {{ row.pay_date ? moment(row.pay_date).format('DD/MM/YYYY') : '—' }}
          </div>
          <div class="repbok-cell" data-title="Usuario">
            {{ row.user?.name ?? '—' }}
          </div>
          <div class="repbok-cell" data-title="Depto." style="text-transform:uppercase">
            {{ row.quotas?.[0]?.departament?.number ?? '—' }}
          </div>
          <div class="repbok-cell" data-title="Cuota">
            {{ row.quotas?.[0]?.month_label ?? '—' }}
          </div>
          <div class="repbok-cell" data-title="Monto">
            S/. {{ Number(row.amount || 0).toFixed(2) }}
          </div>
          <div class="repbok-cell" data-title="Método">
            {{ row.pay_method?.name ?? '—' }}
          </div>
          <div class="repbok-cell" data-title="Referencia">
            {{ row.reference ?? '—' }}
          </div>
          <div class="repbok-cell" data-title="Estado">
            <q-chip :color="row.status_color" text-color="white" size="sm" class="text-weight-bold">
              {{ row.status_label }}
            </q-chip>
          </div>
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else-if="!loading" class="text-center text-grey-6 q-py-xl">
        <q-icon name="eva-search-outline" size="4rem" color="grey" />
        <div class="text-h6 q-mt-sm">No se encontraron pagos</div>
        <div class="text-caption">Intenta ajustar los filtros de búsqueda</div>
      </div>
    </div>

    <!-- Paginación -->
    <div v-if="rows.length > 0" class="row justify-end q-mt-lg">
      <q-pagination
        v-model="pagination.page"
        :max="totalPages"
        :max-pages="6"
        boundary-numbers
        direction-links
        color="primary"
        @update:model-value="onPageChange"
      />
    </div>
  </div>
</template>

<style>
.form__inputsReport .q-field__inner {
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
  margin: 0 0 20px 0;
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

.repbok-row.repbok-header .repbok-cell.sortable {
  cursor: pointer;
  user-select: none;
}

.repbok-row.repbok-header .repbok-cell.sortable:hover {
  opacity: 0.85;
}

.repbok-row.repbok-header .repbok-cell.active-sort {
  text-decoration: underline;
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
}

@media screen and (max-width: 580px) {
  .repbok-cell {
    padding: 2px 16px;
    display: block;
  }
}
</style>
