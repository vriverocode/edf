<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useQuotaStore } from '@/services/store/quota.store'
import { useQuasar } from 'quasar'

const quotaStore = useQuotaStore()
const $q = useQuasar()

const loading = ref(false)
const delinquents = ref([])
const expandedRows = ref({})
const selectedRows = ref([])
const sendingReminder = ref(false)
const showReminderDialog = ref(false)
const reminderMessage = ref('')

const totalDelinquentAmount = computed(() => {
  return delinquents.value.reduce((sum, d) => sum + (Number(d.total_debt) || 0), 0)
})

const totalDelinquentsCount = computed(() => delinquents.value.length)

const selectedCount = computed(() => selectedRows.value.length)

const fetchDelinquents = async () => {
  loading.value = true
  try {
    const response = await quotaStore.getDelinquentsReport()
    if (response?.code === 200) {
      delinquents.value = response.data || []
      selectedRows.value = []
    }
  } catch (e) {
    delinquents.value = []
    selectedRows.value = []
    Notify.create({ color: 'negative', message: 'Error al cargar morosos' })
  } finally {
    loading.value = false
  }
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const getTypeLabels = (types) => {
  const labels = {
    user_status: 'Estado: Moroso',
    overdue_quotas: 'Cuotas >2 meses',
  }
  return types.map(t => labels[t] || t).join(' · ')
}

const getTypeColors = (types) => {
  if (types.includes('user_status') && types.includes('overdue_quotas')) return 'orange-8'
  if (types.includes('user_status')) return 'negative'
  if (types.includes('overdue_quotas')) return 'warning'
  return 'grey'
}

const getQuotaDetail = (row) => {
  if (row.quotas_count === 1 && row.quotas.length === 1) {
    const q = row.quotas[0]
    return `${q.department} - ${q.month_label} (${formatMoney(q.amount)}) - Vence: ${q.due_date}`
  }
  return null
}

const toggleExpand = (row) => {
  expandedRows.value = { ...expandedRows.value, [row.user_id]: !expandedRows.value[row.user_id] }
}

const isExpanded = (row) => expandedRows.value[row.user_id] === true

const toggleSelectAll = () => {
  if (selectedRows.value.length === delinquents.value.length) {
    selectedRows.value = []
  } else {
    selectedRows.value = delinquents.value.map(d => d.user_id)
  }
}

const toggleSelectRow = (userId) => {
  const idx = selectedRows.value.indexOf(userId)
  if (idx === -1) {
    selectedRows.value.push(userId)
  } else {
    selectedRows.value.splice(idx, 1)
  }
}

const isSelected = (userId) => selectedRows.value.includes(userId)

const openReminderDialog = () => {
  if (selectedRows.value.length === 0) {
    Notify.create({ color: 'warning', message: 'Seleccione al menos un moroso' })
    return
  }
  reminderMessage.value = ''
  showReminderDialog.value = true
}

const sendReminder = async () => {
  if (selectedRows.value.length === 0) return
  
  sendingReminder.value = true
  try {
    const response = await quotaStore.sendDelinquentReminder(selectedRows.value, reminderMessage.value || null)
    if (response?.code === 200) {
      Notify.create({ color: 'positive', message: response.data.message || 'Recordatorios enviados correctamente' })
      showReminderDialog.value = false
      selectedRows.value = []
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: e || 'Error al enviar recordatorios' })
  } finally {
    sendingReminder.value = false
  }
}

onMounted(fetchDelinquents)
</script>

<template>
  <div class="md:px-36 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="row q-mb-md justify-between items-center">
      <div class="text-black text-h5 text-bold">Reporte de Morosos</div>
      <div class="row items-center q-gutter-sm">
        <q-btn v-if="selectedCount > 0" label="Enviar recordatorio" color="primary" icon="eva-mail-outline" @click="openReminderDialog" />
        <q-btn label="Actualizar" color="primary" @click="fetchDelinquents" :loading="loading" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>

    <div v-else>
      <div v-if="delinquents.length > 0" class="row q-mb-md">
        <div class="col-12 col-md-4 mt-3 px-3">
          <q-card flat bordered class="bg-negative text-white">
            <q-card-section>
              <div class="text-caption">Total Morosos</div>
              <div class="text-h4 text-bold">{{ totalDelinquentsCount }}</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4 mt-3 px-3">
          <q-card flat bordered class="bg-warning text-white">
            <q-card-section>
              <div class="text-caption">Total Deuda Pendiente</div>
              <div class="text-h4 text-bold">{{ formatMoney(totalDelinquentAmount) }}</div>
            </q-card-section>
          </q-card>
        </div>
        <div class="col-12 col-md-4 mt-3 px-3">
          <q-card flat bordered class="bg-orange-8 text-white">
            <q-card-section>
              <div class="text-caption">Con Cuotas Vencidas >2m</div>
              <div class="text-h4 text-bold">{{ delinquents.filter(d => d.types.includes('overdue_quotas')).length }}</div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <div v-if="delinquents.length === 0" class="text-center text-grey-6 q-py-xl">
        <q-icon name="eva-checkmark-circle-2" size="4rem" color="positive" />
        <div class="text-h6 q-mt-sm">¡No hay morosos registrados!</div>
      </div>

<!-- Tabla estilo repbok (igual que reportBookings) -->
          <div v-else class="repbok-wrapper">
            <div class="repbok-table">
              <!-- Header -->
              <div class="repbok-row repbok-header">
                <div class="repbok-cell" style="width: 50px" data-title="Seleccionar">
                  <q-checkbox dense :model-value="selectedCount === delinquents.length && delinquents.length > 0" @update:model-value="toggleSelectAll" />
                </div>
                <div class="repbok-cell" style="width: 40px" data-title="Acciones">
                  <q-tooltip content="Expandir/colapsar detalle de cuotas" />
                </div>
                <div class="repbok-cell" data-title="Propietario / Inquilino">
                  Propietario / Inquilino
                  <q-tooltip content="Nombre del propietario o inquilino moroso" />
                </div>
                <div class="repbok-cell" data-title="DNI">
                  DNI
                  <q-tooltip content="Documento de identidad" />
                </div>
                <div class="repbok-cell" data-title="Contacto">
                  Contacto
                  <q-tooltip content="Email y teléfono de contacto" />
                </div>
                <div class="repbok-cell" data-title="Departamentos">
                  Departamentos
                  <q-tooltip content="Departamentos asociados al moroso" />
                </div>
                <div class="repbok-cell" data-title="Tipo Morosidad">
                  Tipo Morosidad
                  <q-tooltip content="Causa de la morosidad: estado de usuario y/o cuotas vencidas >2 meses" />
                </div>
                <div class="repbok-cell" data-title="Deuda Total" style="text-align: right">
                  Deuda Total
                  <q-tooltip content="Suma total de deuda pendiente en soles" />
                </div>
                <div class="repbok-cell" data-title="N° Cuotas" style="text-align: center">
                  N° Cuotas
                  <q-tooltip content="Cantidad de cuotas pendientes de pago" />
                </div>
              </div>

              <!-- Filas de datos -->
              <div class="repbok-row" v-for="row in delinquents" :key="row.user_id" :class="{ 'repbok-selected': isSelected(row.user_id) }">
                <div class="repbok-cell" style="width: 50px" data-title="Seleccionar">
                  <q-checkbox dense :model-value="isSelected(row.user_id)" @update:model-value="val => toggleSelectRow(row.user_id)" />
                </div>
                <div class="repbok-cell" style="width: 40px" data-title="Acciones">
                  <q-btn flat dense :icon="isExpanded(row) ? 'eva-chevron-up' : 'eva-chevron-down'" @click="toggleExpand(row)" aria-label="Expandir" />
                </div>
                <div class="repbok-cell" data-title="Propietario / Inquilino">
                  <div class="text-weight-bold">{{ row.name }}</div>
                </div>
                <div class="repbok-cell" data-title="DNI">{{ row.dni || '—' }}</div>
                <div class="repbok-cell" data-title="Contacto">
                  <div v-if="row.email" class="text-caption">{{ row.email }}</div>
                  <div v-if="row.phone" class="text-caption">{{ row.phone }}</div>
                </div>
                <div class="repbok-cell" data-title="Departamentos">
                  <q-chip v-for="dept in row.departments" :key="dept" size="sm" color="primary" text-color="white" class="q-mr-xs q-mb-xs">{{ dept }}</q-chip>
                  <div v-if="!row.departments.length" class="text-grey-6 text-caption">—</div>
                </div>
                <div class="repbok-cell" data-title="Tipo Morosidad">
                  <q-chip :color="getTypeColors(row.types)" size="sm" text-color="white" class="q-mb-xs">{{ getTypeLabels(row.types) }}</q-chip>
                </div>
                <div class="repbok-cell" data-title="Deuda Total" style="text-align: right; font-weight: bold">{{ formatMoney(row.total_debt) }}</div>
                <div class="repbok-cell" data-title="N° Cuotas" style="text-align: center">
                  <div>{{ row.quotas_count }}</div>
                  <div v-if="getQuotaDetail(row)" class="text-caption text-grey-7 q-mt-xs" style="font-size: 11px;">{{ getQuotaDetail(row) }}</div>
                </div>
              </div>

              <!-- Filas expandidas (detalle de cuotas) -->
              <div 
                class="repbok-row repbok-expanded" 
                v-for="row in delinquents" 
                :key="'expand-' + row.user_id"
                v-show="isExpanded(row)"
              >
                <div class="repbok-cell" style="padding: 20px; text-align: center;" colspan="8">
                  <div v-if="row.types.includes('overdue_quotas') && row.quotas.length">
                    <div class="text-h6 q-mb-md" style="text-align: left;">Detalle de Cuotas Pendientes (>2 meses)</div>
                    <q-table
                      flat
                      bordered
                      :rows="row.quotas"
                      row-key="id"
                      virtual-scroll
                      style="width: 100%; display: inline-table;"
                    >
                      <template v-slot:header="hprops">
                        <q-tr :props="hprops">
                          <q-th key="dept" :props="hprops">Departamento</q-th>
                          <q-th key="month" :props="hprops" class="text-center">Mes</q-th>
                          <q-th key="due" :props="hprops" class="text-center">Fecha Vencimiento</q-th>
                          <q-th key="amount" :props="hprops" class="text-right">Monto</q-th>
                        </q-tr>
                      </template>
                      <template v-slot:body="hprops">
                        <q-tr :props="hprops">
                          <q-td key="dept">{{ hprops.row.department }}</q-td>
                          <q-td key="month" class="text-center">{{ hprops.row.month_label }}</q-td>
                          <q-td key="due" class="text-center">{{ hprops.row.due_date }}</q-td>
                          <q-td key="amount" class="text-right text-weight-bold">{{ formatMoney(hprops.row.amount) }}</q-td>
                        </q-tr>
                      </template>
                    </q-table>
                  </div>
                  <div v-else class="text-grey-6 text-center q-py-md">Sin detalle de cuotas vencidas</div>
                </div>
              </div>

            </div>
          </div>

      <!-- Paginación (si se necesita en el futuro) -->
      <!-- <div class="row justify-end q-mt-lg" style="display: flex !important;">
        <q-pagination v-model="pagination.page" :max="totalPages" color="primary" @update:model-value="onPageChange" />
      </div> -->

    </div>
  </div>

  <!-- Dialog Enviar Recordatorio -->
  <q-dialog v-model="showReminderDialog" persistent>
    <q-card style="min-width: 500px;">
      <q-card-section>
        <div class="text-h6">Enviar recordatorio a {{ selectedCount }} moroso{{ selectedCount > 1 ? 's' : '' }}</div>
      </q-card-section>

      <q-card-section class="q-pt-none">
        <q-input
          v-model="reminderMessage"
          type="textarea"
          label="Mensaje personalizado (opcional)"
          hint="Si deja vacío, se enviará un mensaje predeterminado con el detalle de la deuda"
          rows="5"
        />
      </q-card-section>

      <q-card-actions align="right">
        <q-btn flat label="Cancelar" color="primary" v-close-popup />
        <q-btn 
          label="Enviar" 
          color="primary" 
          :loading="sendingReminder" 
          @click="sendReminder" 
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<style>
.rounded-borders { border-radius: 0.5rem; }
</style>

<style scoped lang="scss">
/* Estilos adaptados con el prefijo repbok- (igual que reportBookings) */

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

.repbok-row.repbok-expanded {
  background: #fafafa;
}

.repbok-row.repbok-selected {
  background: #e3f2fd !important;
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