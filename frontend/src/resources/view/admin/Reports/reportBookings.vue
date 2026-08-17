
<script setup>
import { ref, computed, onMounted } from 'vue'
import { useReportStore } from '@/services/store/report.store'
import { useQuasar } from 'quasar'
import moment from 'moment'
import ReportFilterModal from '@/components/reports/reportFilterModal.vue'
import { useRoute, useRouter } from 'vue-router'

const $q = useQuasar()
const reportStore = useReportStore()
const route = useRoute()
const router = useRouter()

const loading = ref(false)
const loadingMetrics = ref(false)
const exporting = ref(false)
const rows = ref([])
const metrics = ref({ top_areas: [], top_dias: [] })
const pagination = ref({ page: 1, rowsNumber: 0, rowsPerPage: 25 })
const search = ref('')
const showFilter = ref(false)
const searchTimeout = ref(null)

const now = new Date()
const quickMonth = ref(null)
const quickYear = ref(now.getFullYear())

const monthOptions = [
  { label: 'Enero', value: 1 }, { label: 'Febrero', value: 2 }, { label: 'Marzo', value: 3 },
  { label: 'Abril', value: 4 }, { label: 'Mayo', value: 5 }, { label: 'Junio', value: 6 },
  { label: 'Julio', value: 7 }, { label: 'Agosto', value: 8 }, { label: 'Septiembre', value: 9 },
  { label: 'Octubre', value: 10 }, { label: 'Noviembre', value: 11 }, { label: 'Diciembre', value: 12 },
]

const statusOptions = [
  { label: 'Todos', value: -1 },
  { label: 'Cancelada', value: 0 },
  { label: 'Pago pendiente', value: 1 },
  { label: 'Pendiente de aprob.', value: 2 },
  { label: 'Exitoso', value: 3 },
  { label: 'Completada', value: 4 },
  { label: 'Pend. reembolso', value: 5 },
  { label: 'Pend. devolución', value: 6 },
]

const filters = ref({
  status: -1,
  area_id: null,
  date_from: null,
  date_to: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  include_cancelled: false,
})

function spanishDayName(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr + 'T12:00:00')
    .toLocaleDateString('es-ES', { weekday: 'long' })
    .replace(/^\w/, c => c.toUpperCase())
}

const stats = computed(() => [
  { label: 'Total', value: metrics.value.total, color: 'text-primary' },
  { label: 'Canceladas', value: metrics.value.canceladas, color: 'text-negative' },
  { label: 'Pend. Pago', value: metrics.value.pendientes_pago, color: 'text-warning' },
  { label: 'Pend. Aprob.', value: metrics.value.pendientes_aprob, color: 'text-warning' },
  { label: 'Exitosas', value: metrics.value.exitosas, color: 'text-positive' },
  { label: 'Completadas', value: metrics.value.completadas, color: 'text-teal-8' },
  { label: 'Pend. Reembolso', value: metrics.value.pend_reembolso, color: 'text-orange-8' },
])

const hasActiveFilter = computed(() => {
  return filters.value.status !== -1
    || filters.value.area_id !== null
    || filters.value.date_from !== null
    || filters.value.date_to !== null
    || filters.value.include_cancelled
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.status !== 4) count++
  if (filters.value.area_id !== null) count++
  if (filters.value.date_from !== null) count++
  if (filters.value.date_to !== null) count++
  if (filters.value.include_cancelled) count++
  return count
})

function payStatusLabel(pay) {
  if (!pay) return 'Sin pago'
  const map = { 0: 'Anulado', 1: 'Pendiente', 2: 'Aprobado', 3: 'Exitoso' }
  return map[pay.status] || '—'
}

function payStatusColor(pay) {
  if (!pay) return 'grey-5'
  const map = { 0: 'negative', 1: 'warning', 2: 'primary', 3: 'positive' }
  return map[pay.status] || 'grey'
}
const totalPages = computed(() => {
  const total = Number(pagination.value.rowsNumber) || 0
  const perPage = Number(pagination.value.rowsPerPage) || 25
  // Math.max asegura que el número mínimo de páginas sea siempre 1, evitando que Quasar se bloquee
  return Math.max(1, Math.ceil(total / perPage))
})

function onPageChange(newPage) {
  pagination.value.page = newPage
  syncToUrl()
  loadData()
}
function onQuickFilterChange() {
  pagination.value.page = 1
  updateDateRange()
  syncToUrl()
  loadData()
  loadMetrics()
}

function updateDateRange() {
  if (quickMonth.value && quickYear.value) {
    const m = String(quickMonth.value).padStart(2, '0')
    const y = quickYear.value
    filters.value.date_from = `${y}-${m}-01`
    const lastDay = moment(`${y}-${m}-01`).endOf('month').format('DD')
    filters.value.date_to = `${y}-${m}-${lastDay}`
  } else {
    filters.value.date_from = null
    filters.value.date_to = null
  }
}

function onSearchChange(val) {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    pagination.value.page = 1
    syncToUrl()
    loadData()
  }, 400)
}

function onUpdateFilters(newFilters) {
  filters.value = newFilters
  pagination.value.page = 1
  syncToUrl()
  loadData()
  loadMetrics()
}

function syncToUrl() {
  const query = { ...route.query }
  const set = (k, v) => {
    if (v === '' || v === null || v === undefined || v === false) delete query[k]
    else query[k] = v
  }
  set('search', search.value)
  set('quickMonth', quickMonth.value)
  set('quickYear', quickYear.value)
  set('status', filters.value.status)
  set('area_id', filters.value.area_id)
  set('date_from', filters.value.date_from)
  set('date_to', filters.value.date_to)
  set('sort_by', filters.value.sort_by)
  set('sort_dir', filters.value.sort_dir)
  set('include_cancelled', filters.value.include_cancelled ? 1 : undefined)
  query.page = pagination.value.page
  router.replace({ query })
}

function restoreFromQuery() {
  if (route.query.page) pagination.value.page = Number(route.query.page)
  if (route.query.search !== undefined) search.value = route.query.search
  if (route.query.quickMonth !== undefined) quickMonth.value = Number(route.query.quickMonth) || null
  if (route.query.quickYear !== undefined) quickYear.value = Number(route.query.quickYear) || now.getFullYear()
  if (route.query.status !== undefined) filters.value.status = Number(route.query.status)
  if (route.query.area_id !== undefined) filters.value.area_id = route.query.area_id === '' ? null : Number(route.query.area_id)
  if (route.query.date_from !== undefined) filters.value.date_from = route.query.date_from || null
  if (route.query.date_to !== undefined) filters.value.date_to = route.query.date_to || null
  if (route.query.sort_by !== undefined) filters.value.sort_by = route.query.sort_by
  if (route.query.sort_dir !== undefined) filters.value.sort_dir = route.query.sort_dir
  if (route.query.include_cancelled !== undefined) filters.value.include_cancelled = route.query.include_cancelled === '1' || route.query.include_cancelled === 'true'
}

async function loadData() {
  loading.value = true
  try {
    const params = {
      search: search.value || null,
      status: filters.value.status,
      area_id: filters.value.area_id,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      sort_by: filters.value.sort_by,
      sort_dir: filters.value.sort_dir,
      include_cancelled: filters.value.include_cancelled,
      per_page: pagination.value.rowsPerPage,
      page: pagination.value.page,
    }
    const res = await reportStore.getBookings(params)
    const data = res.data
    rows.value = data.data
    pagination.value.page = data.current_page
    pagination.value.rowsNumber = data.total
    pagination.value.rowsPerPage = data.per_page
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al cargar reservas' })
  } finally {
    loading.value = false
  }
}

async function loadMetrics() {
  loadingMetrics.value = true
  try {
    const res = await reportStore.getBookingsMetrics({
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
    })
    metrics.value = res.data
  } catch {
    metrics.value = { top_areas: [], top_dias: [] }
  } finally {
    loadingMetrics.value = false
  }
}

async function handleExport() {
  exporting.value = true
  try {
    const params = {
      search: search.value || null,
      status: filters.value.status,
      area_id: filters.value.area_id,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
    }
    await reportStore.exportBookings(params)
    $q.notify({ type: 'positive', message: 'Reporte exportado exitosamente' })
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al exportar' })
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  restoreFromQuery()
  if (route.query.quickMonth !== undefined) updateDateRange()
  loadData()
  loadMetrics()
})
</script>

<template>
  <div class="q-py-md md:px-36 px-2">
    <div class="row q-mb-md q-col-gutter-sm items-center">
      <div class="col-12 col-md-2">
        <q-select v-model="quickMonth" :options="monthOptions" option-label="label" option-value="value"
          emit-value map-options dense borderless class="form__inputsRReportBooking" label="Mes" clearable color="primary"
          @update:model-value="onQuickFilterChange" />
      </div>
      <div class="col-6 col-md-1">
        <q-input v-model.number="quickYear" type="number" dense borderless class="form__inputsRReportBooking" label="Año" color="primary"
          @update:model-value="onQuickFilterChange" />
      </div>
      <div class="col-6 col-md-2">
        <q-select v-model="filters.status" :options="statusOptions" option-label="label" option-value="value"
          emit-value map-options dense borderless class="form__inputsRReportBooking" label="Estado" clearable color="primary"
          @update:model-value="onQuickFilterChange" />
      </div>
      <div class="col-12 col-md-3">
        <q-input v-model="search" dense borderless class="form__inputsRReportBooking w-full md:mr-3" label="Buscar reserva, usuario, depto, área..."
          clearable color="primary" style="" @update:model-value="onSearchChange">
          <template #prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
      <div class="col-md-4 col-12 row items-center">
        <div class="col-4 col-md-4">
          <q-checkbox :model-value="filters.include_cancelled" label="Incluir canceladas"
            @update:model-value="val => { filters.include_cancelled = val; syncToUrl(); loadData(); loadMetrics() }" />
        </div>
        <div class="col-2 col-md-2">
          <q-btn :color="hasActiveFilter ? 'primary' : 'grey-7'" outline
            icon="eva-funnel-outline"
            :label="hasActiveFilter ? `(${activeFilterCount})` : ''"
            @click="showFilter = true">
            <q-badge v-if="hasActiveFilter" color="red" floating rounded>{{ activeFilterCount }}</q-badge>
          </q-btn>
        </div>
        <div class="col-6 ">
          <q-btn color="green" unelevated label="Exportar Excel" icon="eva-download-outline"
            :loading="exporting" @click="handleExport" />
        </div>
      </div>
    </div>

    <div v-if="!loadingMetrics" class="row md:pb-5 pb-2">
      <div class="col-4 px-2 my-1 md:my-0 col-md" v-for="stat in stats" :key="stat.label">
        <q-card flat bordered class="q-pa-sm text-center">
          <div class="text-h5 text-weight-bold" :class="stat.color">{{ stat.value }}</div>
          <div class="text-caption text-grey-7">{{ stat.label }}</div>
        </q-card>
      </div>
    </div>

    <div v-if="!loadingMetrics" class="row q-col-gutter-sm q-mb-md">
      <div class="col-6">
        <q-card flat bordered class="q-pa-md">
          <div class="text-subtitle2 text-grey-8 q-mb-sm">Top 5 áreas más reservadas</div>
          <div v-if="metrics.top_areas?.length === 0" class="text-grey-5 text-center q-py-md">Sin datos</div>
          <div v-for="(area, i) in metrics.top_areas" :key="i" class="q-mb-sm">
            <div class="row items-center q-mb-xs">
              <div class="col text-weight-medium text-grey-8 text-no-wrap ellipsis">#{{ i + 1 }} {{ area.name }}</div>
              <div class="col-auto text-grey-7 text-caption q-ml-sm">{{ area.total }}</div>
              <div class="col-auto text-primary text-weight-medium q-ml-xs" style="min-width: 44px; text-align: right">{{ area.percentage }}%</div>
            </div>
            <q-linear-progress :value="area.percentage / 100" color="primary" class="rounded-borders" style="height: 8px" />
          </div>
        </q-card>
      </div>
      <div class="col-6">
        <q-card flat bordered class="q-pa-md">
          <div class="text-subtitle2 text-grey-8 q-mb-sm">Reservas por día de la semana</div>
          <div v-if="metrics.top_dias?.length === 0" class="text-grey-5 text-center q-py-md">Sin datos</div>
          <div v-for="(dia, i) in metrics.top_dias" :key="i" class="q-mb-sm">
            <div class="row items-center q-mb-xs">
              <div class="col text-weight-medium text-grey-8">{{ dia.day_name }}</div>
              <div class="col-auto text-grey-7 text-caption q-ml-sm">{{ dia.total }}</div>
              <div class="col-auto text-primary text-weight-medium q-ml-xs" style="min-width: 44px; text-align: right">{{ dia.percentage }}%</div>
            </div>
            <q-linear-progress :value="dia.percentage / 100" color="primary" class="rounded-borders" style="height: 8px" />
          </div>
        </q-card>
      </div>
    </div>

    <div class="text-grey-9 my-3 text-title-squad text-bold">Listado de reservas</div>
    <div class="pb-12 relative-position">
      
      <!-- Overlay de carga -->
      <q-inner-loading :showing="loading" color="primary" class="z-top">
        <q-spinner-dots size="50px" color="primary" />
      </q-inner-loading>

      <!-- Estado vacío -->
      <div v-if="!loading && rows.length === 0" class="text-center q-pa-lg w-full">
        <q-icon name="eva-inbox-outline" size="48px" color="grey-4" />
        <div class="text-grey-6 q-mt-sm">No se encontraron reservas</div>
      </div>

      <!-- Implementación con prefijo repbok- -->
      <div v-if="rows.length > 0" class="repbok-wrapper">
        <div class="repbok-table">
          
          <div class="repbok-row repbok-header">
            <div class="repbok-cell">Día</div>
            <div class="repbok-cell">Fec. Uso</div>
            <div class="repbok-cell">Horario</div>
            <div class="repbok-cell">Amb. Común</div>
            <div class="repbok-cell">Dpto</div>
            <div class="repbok-cell">Fec. Registro</div>
            <div class="repbok-cell">Usuario</div>
            <div class="repbok-cell">Cod Pago</div>
            <div class="repbok-cell">Total</div>
            <div class="repbok-cell">Fec. Pago</div>
            <div class="repbok-cell">Estado</div>
          </div>
          
          <div class="repbok-row" v-for="row in rows" :key="row.id">
            <div class="repbok-cell" data-title="Día">
              {{ spanishDayName(row.date) }}
            </div>
            <div class="repbok-cell" data-title="Fec. Uso">
              {{ row.date }}
            </div>
            <div class="repbok-cell" data-title="Horario">
              {{ row.time_from }} - {{ row.time_to }}
            </div>
            <div class="repbok-cell" data-title="Amb. Común">
              {{ row.comun_area?.name || '—' }}
            </div>
            <div class="repbok-cell" data-title="Dpto">
              {{ row.departament?.number || '—' }}
            </div>
            <div class="repbok-cell" data-title="Fec. Registro">
              {{ row.created_at ? moment(row.created_at).format('DD/MM/YYYY') : '—' }}
            </div>
            <div class="repbok-cell" data-title="Usuario">
              {{ row.user?.name || '—' }}
            </div>
            <div class="repbok-cell" data-title="Cod Pago">
              {{ row.booking_number || '—' }}
            </div>
            <div class="repbok-cell" data-title="Total">
              S/ {{ Number(row.amount).toFixed(2) }}
            </div>
            <div class="repbok-cell" data-title="Fec. Pago">
              {{ row.pay?.pay_date ? moment(row.pay.pay_date).format('DD/MM/YYYY') : '—' }}
            </div>
            <div class="repbok-cell" data-title="Estado">
              <q-badge :color="row.status_color" class="q-px-sm q-py-xs">{{ row.status_label }}</q-badge>
            </div>
          </div>
          
        </div>
      </div>

      <!-- Controles de Paginación -->
      <div v-if="rows.length > 0" class="row justify-end q-mt-lg" style="display: flex !important;">
        <q-pagination
          v-model="pagination.page"
          :max="Math.max(1, Math.ceil(pagination.rowsNumber / pagination.rowsPerPage))"
          :max-pages="6"
          boundary-numbers
          direction-links
          color="primary"
          @update:model-value="onPageChange"
        />
      </div>

    </div>

    <ReportFilterModal :dialog="showFilter" :filters="filters" @close-modal="showFilter = false"
      @update-list="onUpdateFilters" />
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
/* Estilos adaptados con el prefijo repbok- */

.repbok-wrapper {
  margin: 0 auto;
  width: 100%;
  overflow-x: auto;
}

.repbok-table {
  margin: 0 0 40px 0;
  width: 100%;
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
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

.repbok-row.repbok-green {
  background: #27ae60;
}

.repbok-row.repbok-blue {
  background: #2980b9;
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