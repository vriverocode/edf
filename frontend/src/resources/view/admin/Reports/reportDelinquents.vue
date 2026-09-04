<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useReportStore } from '@/services/store/report.store'
import { useQuotaStore } from '@/services/store/quota.store'

const $q = useQuasar()
const reportStore = useReportStore()
const quotaStore = useQuotaStore()

const loading = ref(false)
const loadingMetrics = ref(false)
const exporting = ref(false)
const rows = ref([])
const metrics = ref({ total_delinquents: 0, total_debt: 0, total_overdue_quotas: 0 })
const pagination = ref({ page: 1, rowsNumber: 0, rowsPerPage: 15 })
const search = ref('')
const searchTimeout = ref(null)
const expandedRows = ref({})
const sendingForUser = ref(null)

const stats = computed(() => [
  { label: 'Total Morosos', value: metrics.value.total_delinquents, color: 'text-negative', icon: 'eva-person-remove-outline' },
  { label: 'Deuda Total', value: formatMoney(metrics.value.total_debt), color: 'text-warning', icon: 'eva-alert-triangle-outline' },
  { label: 'Cuotas Vencidas >2m', value: metrics.value.total_overdue_quotas, color: 'text-orange-8', icon: 'eva-clock-outline' },
])

const totalPages = computed(() => {
  const total = Number(pagination.value.rowsNumber) || 0
  const perPage = Number(pagination.value.rowsPerPage) || 15
  return Math.max(1, Math.ceil(total / perPage))
})

function onPageChange(newPage) {
  pagination.value.page = newPage
  loadData()
}

function onSearchChange() {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    pagination.value.page = 1
    loadData()
  }, 400)
}

async function loadData() {
  loading.value = true
  try {
    const params = {
      search: search.value || null,
      per_page: pagination.value.rowsPerPage,
      page: pagination.value.page,
    }
    const res = await reportStore.getDelinquents(params)
    const data = res.data
    rows.value = data.data
    pagination.value.page = data.current_page
    pagination.value.rowsNumber = data.total
    pagination.value.rowsPerPage = data.per_page
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al cargar morosos' })
  } finally {
    loading.value = false
  }
}

async function loadMetrics() {
  loadingMetrics.value = true
  try {
    const res = await reportStore.getDelinquentsMetrics()
    metrics.value = res.data
  } catch {
    metrics.value = { total_delinquents: 0, total_debt: 0, total_overdue_quotas: 0 }
  } finally {
    loadingMetrics.value = false
  }
}

async function handleExport() {
  exporting.value = true
  try {
    await reportStore.exportDelinquents({ search: search.value || null })
    $q.notify({ type: 'positive', message: 'Reporte exportado exitosamente' })
  } catch (err) {
    $q.notify({ type: 'negative', message: err || 'Error al exportar' })
  } finally {
    exporting.value = false
  }
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const getQuotaDetail = (row) => {
  if (row.quotas_count === 1 && row.quotas.length === 1) {
    const q = row.quotas[0]
    return `${q.department} - ${q.month_label}`
  }
  return null
}

const getPaymentResponsible = (row) => {
  if (!row.quotas.length) return null
  const first = row.quotas[0]
  const roles = [...new Set(row.quotas.map(q => q.payment_responsible_role))]
  return {
    role: roles.length === 1 ? roles[0] : 'Mixto',
    name: first.payment_responsible_name,
  }
}

const toggleExpand = (userId) => {
  expandedRows.value = { ...expandedRows.value, [userId]: !expandedRows.value[userId] }
}

const isExpanded = (userId) => expandedRows.value[userId] === true

function confirmReminder(row) {
  $q.dialog({
    title: 'Enviar recordatorio',
    message: `¿Enviar recordatorio de cuotas pendientes a ${row.name}?`,
    prompt: {
      model: '',
      type: 'text',
      label: 'Mensaje personalizado (opcional)',
    },
    cancel: { label: 'Cancelar', flat: true, color: 'primary' },
    ok: { label: 'Enviar', color: 'primary' },
  }).onOk(async (customMessage) => {
    sendingForUser.value = row.user_id
    try {
      const response = await quotaStore.sendDelinquentReminder(
        [row.user_id],
        customMessage || null
      )
      if (response?.code === 200) {
        $q.notify({ type: 'positive', message: response.data.message || 'Recordatorio enviado' })
      }
    } catch (e) {
      $q.notify({ type: 'negative', message: e || 'Error al enviar recordatorio' })
    } finally {
      sendingForUser.value = null
    }
  })
}

onMounted(() => {
  loadData()
  loadMetrics()
})
</script>

<template>
  <div class="q-py-md md:px-36 px-2">
    <div class="row q-mb-md q-col-gutter-sm items-center">
      <div class="col-12 col-md-4">
        <q-input v-model="search" dense borderless class="form__inputsRDelinquents" label="Buscar por nombre, DNI o departamento..."
          clearable color="primary" @update:model-value="onSearchChange">
          <template #prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
      <div class="col-md-3 col-6">
        <q-btn color="green" unelevated label="Exportar Excel" icon="eva-download-outline"
          class="full-width" :loading="exporting" @click="handleExport" />
      </div>
      <div class="col-md-2 col-6">
        <q-btn color="primary" unelevated label="Actualizar" icon="eva-refresh-outline"
          class="full-width" :loading="loading" @click="loadData(); loadMetrics()" />
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

    <div class="text-grey-9 my-3 text-title-squad text-bold">Listado de morosos</div>
    <div class="pb-12 relative-position">

      <q-inner-loading :showing="loading" color="primary" class="z-top">
        <q-spinner-dots size="50px" color="primary" />
      </q-inner-loading>

      <div v-if="!loading && rows.length === 0" class="text-center q-pa-lg w-full">
        <q-icon name="eva-checkmark-circle-2" size="48px" color="positive" />
        <div class="text-grey-6 q-mt-sm">No se encontraron morosos</div>
      </div>

      <div v-if="rows.length > 0" class="repbok-wrapper">
        <div class="repbok-table">

          <div class="repbok-row repbok-header">
            <div class="repbok-cell" style="width: 40px"></div>
            <div class="repbok-cell">Propietario / Doc.</div>
            <div class="repbok-cell">Encargado de Pago</div>
            <div class="repbok-cell">Rol</div>
            <div class="repbok-cell">Contacto</div>
            <div class="repbok-cell">Unidades (Deuda)</div>
            <div class="repbok-cell" style="text-align: center">N° Cuotas</div>
            <div class="repbok-cell" style="text-align: right">Monto Total</div>
            <div class="repbok-cell" style="text-align: center">Acciones</div>
          </div>

          <template v-for="row in rows" :key="row.user_id">
            <div class="repbok-row">
              <div class="repbok-cell" style="width: 40px" data-title="">
                <q-btn v-if="row.types.includes('overdue_quotas') && row.quotas.length"
                  flat dense :icon="isExpanded(row.user_id) ? 'eva-chevron-up' : 'eva-chevron-down'"
                  @click="toggleExpand(row.user_id)" />
              </div>
              <div class="repbok-cell" data-title="Propietario / Doc.">
                <div class="text-weight-bold">{{ row.name }}</div>
                <div class="text-caption text-grey-6">{{ row.dni || '—' }}</div>
              </div>
              <div class="repbok-cell" data-title="Encargado de Pago">
                <template v-if="getPaymentResponsible(row)">
                  <div class="text-caption text-grey-7">{{ getPaymentResponsible(row).name }}</div>
                </template>
                <div v-else class="text-grey-6 text-caption">—</div>
              </div>
              <div class="repbok-cell" data-title="Rol">
                <template v-if="getPaymentResponsible(row)">
                  <q-chip :color="getPaymentResponsible(row).role === 'Inquilino' ? 'teal' : 'orange'" size="sm" text-color="white" class="q-mb-xs">
                    {{ getPaymentResponsible(row).role }}
                  </q-chip>
                </template>
                <div v-else class="text-grey-6 text-caption">—</div>
              </div>
              <div class="repbok-cell" data-title="Contacto">
                <div v-if="row.email" class="text-caption">{{ row.email }}</div>
                <div v-if="row.phone" class="text-caption">{{ row.phone }}</div>
                <div v-if="!row.email && !row.phone" class="text-grey-6">—</div>
              </div>
              <div class="repbok-cell" data-title="Unidades (Deuda)">
                <q-chip v-for="dept in row.departments" :key="dept" size="sm" color="primary" text-color="white" class="q-mr-xs q-mb-xs">{{ dept }}</q-chip>
                <div v-if="!row.departments.length" class="text-grey-6 text-caption">—</div>
              </div>
              <div class="repbok-cell" data-title="N° Cuotas" style="text-align: center">
                <div>{{ row.quotas_count }}</div>
                <div v-if="getQuotaDetail(row)" class="text-caption text-grey-7" style="font-size: 11px;">{{ getQuotaDetail(row) }}</div>
              </div>
              <div class="repbok-cell" data-title="Monto Total" style="text-align: right; font-weight: bold">{{ formatMoney(row.total_debt) }}</div>
              <div class="repbok-cell" data-title="Acciones" style="text-align: center">
                <q-btn round size="xs" icon="eva-bell-outline" color="primary"
                  :loading="sendingForUser === row.user_id"
                  @click="confirmReminder(row)">
                  <q-tooltip>Enviar recordatorio</q-tooltip>
                </q-btn>
              </div>
            </div>

            <div v-if="isExpanded(row.user_id) && row.types.includes('overdue_quotas') && row.quotas.length"
              class="repbok-detail-wrapper">
                <div class="repbok-detail-table">
                  <div class="repbok-detail-header">
                    <div class="repbok-detail-cell">Departamento</div>
                    <div class="repbok-detail-cell repbok-detail-center">Mes</div>
                    <div class="repbok-detail-cell repbok-detail-center">Vencimiento</div>
                    <div class="repbok-detail-cell">Responsable</div>
                    <div class="repbok-detail-cell repbok-detail-right">Monto</div>
                  </div>
                  <div class="repbok-detail-row" v-for="q in row.quotas" :key="q.id">
                    <div class="repbok-detail-cell" data-label="Departamento">{{ q.department }}</div>
                    <div class="repbok-detail-cell repbok-detail-center" data-label="Mes">{{ q.month_label }}</div>
                    <div class="repbok-detail-cell repbok-detail-center" data-label="Vencimiento">{{ q.due_date }}</div>
                    <div class="repbok-detail-cell" data-label="Responsable">
                      <span class="text-caption">{{ q.payment_responsible_name }}</span>
                      <q-chip :color="q.tenant_pays_quota ? 'teal' : 'orange'" size="xs" text-color="white" class="q-ml-xs">{{ q.payment_responsible_role }}</q-chip>
                    </div>
                    <div class="repbok-detail-cell repbok-detail-right text-weight-bold" data-label="Monto">{{ formatMoney(q.amount) }}</div>
                  </div>
                </div>
            </div>
          </template>

        </div>
      </div>

      <div v-if="rows.length > 0" class="row justify-end q-mt-lg" style="display: flex !important;">
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
  </div>
</template>

<style>
.form__inputsRDelinquents .q-field__inner {
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
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
  display: grid;
  grid-template-columns: 40px 1.5fr 1.2fr 0.8fr 1fr 1.5fr 0.8fr 1fr 0.8fr;
}

@media screen and (max-width: 580px) {
  .repbok-table {
    display: block;
  }
}

.repbok-row {
  display: contents;
  background: #f6f6f6;
  margin: 0;
}

.repbok-row:nth-of-type(odd) {
  background: #e9e9e9;
}

.repbok-row.repbok-header {
  font-weight: 900;
  color: #ffffff;
}

.repbok-row.repbok-header .repbok-cell {
  background: $primary;
}

.repbok-row.repbok-expanded {
  background: #fafafa;
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
  display: flex;
  align-items: center;
  vertical-align: middle;
}

@media screen and (max-width: 580px) {
  .repbok-cell {
    padding: 2px 16px;
    display: block;
  }
}

.repbok-detail-wrapper {
  grid-column: 1 / -1;
  background: #fafafa;
  border-top: 1px solid #e0e0e0;
}

.repbok-detail-content {
  padding: 16px 20px;
}

.repbok-detail-table {
  width: 100%;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  overflow: hidden;
}

.repbok-detail-header {
  display: table-row;
  background: #f5f5f5;
}

.repbok-detail-header .repbok-detail-cell {
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  color: #666;
  padding: 8px 12px;
  border-bottom: 2px solid #e0e0e0;
}

.repbok-detail-row {
  display: table-row;
  background: #fff;
}

.repbok-detail-row:nth-of-type(even) {
  background: #fafafa;
}

.repbok-detail-row .repbok-detail-cell {
  padding: 8px 12px;
  border-bottom: 1px solid #eee;
  font-size: 13px;
}

.repbok-detail-cell {
  display: table-cell;
  vertical-align: middle;
}

.repbok-detail-center {
  text-align: center;
}

.repbok-detail-right {
  text-align: right;
}

@media screen and (max-width: 580px) {
  .repbok-detail-header,
  .repbok-detail-row {
    display: block;
  }

  .repbok-detail-header .repbok-detail-cell {
    display: none;
  }

  .repbok-detail-cell {
    display: block;
    padding: 4px 12px;
  }

  .repbok-detail-cell:before {
    content: attr(data-label);
    font-weight: bold;
    font-size: 10px;
    text-transform: uppercase;
    color: #999;
    display: block;
    margin-bottom: 2px;
  }
}
</style>
