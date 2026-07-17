<template>
  <div class="q-py-md px-36">
    <div class="row q-mb-md items-center">
      <div class="col">
        <q-input
          v-model="search"
          outlined
          dense
          placeholder="Buscar reserva, usuario, depto, área..."
          clearable
          class="q-mr-sm"
          style="max-width: 360px"
          @update:model-value="onSearchChange"
        >
          <template #prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
      <div class="col-auto items-center q-gutter-x-sm">
        <q-checkbox :model-value="filters.include_cancelled" label="Incluir canceladas"
          @update:model-value="val => { filters.include_cancelled = val; loadData(); loadMetrics() }" />
        <q-btn
          :color="hasActiveFilter ? 'primary' : 'grey-7'"
          outline
          :label="hasActiveFilter ? `Filtros (${activeFilterCount})` : 'Filtros'"
          @click="showFilter = true"
        >
          <q-badge v-if="hasActiveFilter" color="red" floating rounded>
            {{ activeFilterCount }}
          </q-badge>
        </q-btn>
        <q-btn
          color="green"
          unelevated
          label="Exportar Excel"
          icon="eva-download-outline"
          :loading="exporting"
          @click="handleExport"
        />
      </div>
    </div>

    <div v-if="!loadingMetrics" class="row q-col-gutter-sm q-mb-md">
      <div class="col" v-for="stat in stats" :key="stat.label">
        <q-card flat bordered class="q-pa-sm text-center">
          <div class="text-h5 text-weight-bold" :class="stat.color">
            {{ stat.value }}
          </div>
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
              <div class="col text-weight-medium text-grey-8 text-no-wrap ellipsis">
                #{{ i + 1 }} {{ area.name }}
              </div>
              <div class="col-auto text-grey-7 text-caption q-ml-sm">{{ area.total }}</div>
              <div class="col-auto text-primary text-weight-medium q-ml-xs" style="min-width: 44px; text-align: right">
                {{ area.percentage }}%
              </div>
            </div>
            <q-linear-progress
              :value="area.percentage / 100"
              color="primary"
              class="rounded-borders"
              style="height: 8px"
            />
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
              <div class="col-auto text-primary text-weight-medium q-ml-xs" style="min-width: 44px; text-align: right">
                {{ dia.percentage }}%
              </div>
            </div>
            <q-linear-progress
              :value="dia.percentage / 100"
              color="primary"
              class="rounded-borders"
              style="height: 8px"
            />
          </div>
        </q-card>
      </div>
    </div>
    <div class="text-grey-9 my-3 text-title-squad text-bold">
      Listado de reservas
    </div>
    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      :loading="loading"
      :pagination="pagination"
      :visible-columns="visibleColumns"
      row-key="id"
      @request="onRequest"
      virtual-scroll
      :virtual-scroll-item-size="48"
      :virtual-scroll-sticky-size-start="48"
      class="rounded-borders"
    >
      <template #loading>
        <q-inner-loading showing color="primary">
          <q-spinner-dots size="50px" color="primary" />
        </q-inner-loading>
      </template>

      <template #no-data>
        <div class="text-center q-pa-lg w-full">
          <q-icon name="eva-inbox-outline" size="48px" color="grey-4" />
          <div class="text-grey-6 q-mt-sm">No se encontraron reservas</div>
        </div>
      </template>

      <template #body-cell-status="props">
        <td>
          <q-badge :color="props.row.status_color" class="q-px-sm q-py-xs">
            {{ props.row.status_label }}
          </q-badge>
        </td>
      </template>

      <template #body-cell-amount="props">
        <td class="text-right text-weight-medium">
          S/ {{ Number(props.row.amount).toFixed(2) }}
        </td>
      </template>

      <template #body-cell-date="props">
        <td class="text-no-wrap">{{ props.row.date }}</td>
      </template>

      <template #body-cell-time="props">
        <td class="text-no-wrap">{{ props.row.time_from }} - {{ props.row.time_to }}</td>
      </template>

      <template #body-cell-pay_status="props">
        <td>
          <q-badge :color="payStatusColor(props.row.pay)" class="q-px-sm q-py-xs">
            {{ payStatusLabel(props.row.pay) }}
          </q-badge>
        </td>
      </template>
    </q-table>

    <ReportFilterModal
      :dialog="showFilter"
      :filters="filters"
      @close-modal="showFilter = false"
      @update-list="onUpdateFilters"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useReportStore } from '@/services/store/report.store'
import { useQuasar } from 'quasar'
import ReportFilterModal from '@/components/reports/reportFilterModal.vue'

const $q = useQuasar()
const reportStore = useReportStore()

const loading = ref(false)
const loadingMetrics = ref(false)
const exporting = ref(false)
const rows = ref([])
const metrics = ref({ top_areas: [], top_dias: [] })
const pagination = ref({
  page: 1,
  rowsNumber: 0,
  rowsPerPage: 25,
})
const search = ref('')
const showFilter = ref(false)
const searchTimeout = ref(null)

const filters = ref({
  status: 4,
  area_id: null,
  date_from: null,
  date_to: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
  include_cancelled: false,
})

const columns = [
  { name: 'booking_number', label: 'N° Reserva', field: (r) => '#'.concat(r.booking_number), align: 'left', sortable: false },
  { name: 'user', label: 'Usuario', field: (r) => r.user?.name || '—', align: 'left', sortable: false },
  { name: 'departament', label: 'Depto', field: (r) => r.departament?.number || '—', align: 'left', sortable: false },
  { name: 'area', label: 'Área', field: (r) => r.comun_area?.name || '—', align: 'left', sortable: false },
  { name: 'date', label: 'Fecha', field: 'date', align: 'left', sortable: false },
  { name: 'time', label: 'Horario', field: 'time', align: 'left', sortable: false },
  { name: 'amount', label: 'Monto', field: 'amount', align: 'right', sortable: false },
  { name: 'status', label: 'Estado', field: 'status', align: 'left', sortable: false },
  { name: 'pay_status', label: 'Pago', field: 'pay', align: 'left', sortable: false },
]

const visibleColumns = computed(() => columns.map((c) => c.name))

const stats = computed(() => [
  { label: 'Total', value: metrics.value.total, color: 'text-primary' },
  { label: 'Canceladas', value: metrics.value.canceladas, color: 'text-negative' },
  { label: 'Pend. Pago', value: metrics.value.pendientes_pago, color: 'text-warning' },
  { label: 'Pend. Aprob.', value: metrics.value.pendientes_aprob, color: 'text-warning' },
  { label: 'Exitosas', value: metrics.value.exitosas, color: 'text-positive' },
])

const hasActiveFilter = computed(() => {
  return filters.value.status !== 4
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

function onSearchChange(val) {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    pagination.value.page = 1
    loadData()
  }, 400)
}

function onRequest(props) {
  pagination.value.page = props.pagination.page
  pagination.value.rowsPerPage = props.pagination.rowsPerPage
  loadData()
}

function onUpdateFilters(newFilters) {
  filters.value = newFilters
  pagination.value.page = 1
  loadData()
  loadMetrics()
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
  loadData()
  loadMetrics()
})
</script>
