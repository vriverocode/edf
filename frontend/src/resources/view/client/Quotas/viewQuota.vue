<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import iconsApp from '@/assets/icons/index';
import moment from 'moment';
import voucherModal from '@/components/pay/voucherModal.vue';


const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()

// Estados reactivos
const quotaData = ref(null)
const isLoading = ref(false)
const errorMessage = ref(null)
const showVoucherModal = ref(false)
const maintenanceBreakdown = ref(null)
const waterBreakdown = ref(null)
const consolidatedQuotas = ref([])
const payData = ref(null)

const departmentTypeLabel = (type) => {
  const labels = { 1: 'DPT', 2: 'EST', 3: 'DPO', 4: 'LAV' }
  return labels[type] || 'UNI'
}

const buildBreakdownItem = (quota, isCurrent = false) => ({
  id: quota.id,
  number: quota.departament?.number || quota.departament_number || '—',
  type: quota.departament?.type,
  typeLabel: departmentTypeLabel(quota.departament?.type),
  maintenance_amount: quota.maintenance_amount,
  water_amount: quota.water_amount,
  extra_amount: quota.extra_amount,
  amount: quota.amount,
  has_water_reading: !!quota.water_reading_id,
  water_reading_id: quota.water_reading_id,
  waterReading: quota.waterReading || null,
  isCurrent,
})

const allQuotasForBreakdown = computed(() => {
  if (!quotaData.value) return []
  const current = buildBreakdownItem(quotaData.value, true)
  const others = consolidatedQuotas.value.map(q => buildBreakdownItem(q, false))
  return [current, ...others]
})

const hasConsolidation = computed(() => consolidatedQuotas.value.length > 0)

const totalMaintenance = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.maintenance_amount || 0), 0)
)

const totalWater = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.water_amount || 0), 0)
)

const totalExtras = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.extra_amount || 0), 0)
)
// Función para obtener quota por ID
const fetchQuotaById = async (id) => {
  try {
    isLoading.value = true
    errorMessage.value = null

    const response = await quotaStore.getQuotaById(id)
    quotaData.value = response.data
    consolidatedQuotas.value = response.data.consolidated_quotas || []
    payData.value = response.data.pay

  } catch (err) {
    console.error('Error al obtener la cuota:', err)
    errorMessage.value = err || 'Error al cargar la cuota'
  } finally {
    isLoading.value = false
  }
}

const fetchQuotaBreakdownDetails = async (id) => {
  try {
    const [maintenanceResponse, waterResponse] = await Promise.allSettled([
      quotaStore.getClientMaintenanceDetailByQuotaId(id),
      quotaStore.getClientWaterDetailByQuotaId(id),
    ])
    if (maintenanceResponse.status === 'fulfilled') maintenanceBreakdown.value = maintenanceResponse.value?.data
    if (waterResponse.status === 'fulfilled') waterBreakdown.value = waterResponse.value?.data
  } catch (e) {
    // silencioso
  }
}

// Función para descargar recibo
const downloadReceipt = async () => {
  const currentQuotaId = quotaData.value?.id
  if (!currentQuotaId) return
  const token = localStorage.getItem('access_token')
  try {
    const res = await fetch('/api/bill-invoices/client-download/' + currentQuotaId, {
      headers: { Authorization: 'Bearer ' + token }
    })
    if (!res.ok) return
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'recibo-cuota-' + currentQuotaId + '.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Error al descargar recibo:', e)
  }
}

// Función para ir al inicio
const goToHome = () => {
  router.push('/client/quotas/list')
}

// Obtener el ID del quota desde la URL
const currentQuotaId = route.params.id || route.query.id

// Cargar el quota al montar el componente
onMounted(() => {
  if (currentQuotaId) {
    fetchQuotaById(currentQuotaId).then(() => fetchQuotaBreakdownDetails(currentQuotaId))
  } else {
    errorMessage.value = 'ID de cuota no proporcionado'
  }
})

// Función para recargar el quota
const reloadQuota = () => {
  if (currentQuotaId) {
    maintenanceBreakdown.value = null
    waterBreakdown.value = null
    consolidatedQuotas.value = []
    payData.value = null
    fetchQuotaById(currentQuotaId).then(() => fetchQuotaBreakdownDetails(currentQuotaId))
  }
}
</script>

<template>
  <div class="h-full  relative overflow-hidden">
    <div class="relative  pt-8 pb-0 md:px-6 px-3 h-full" style="overflow: auto;">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />

        <p class="text-gray-600 font-medium">Cargando cuota...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="errorMessage" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ errorMessage }}</p>
        <button @click="reloadQuota"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="quotaData" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full ">
          <div class="row w-full mb-3 items-start">
            <div class="flex flex-col items-start col-md-9 col-6 md:pl-5 pl-3 ">
              <div class="mb-4 pt-5">
                <div class="bg-primary rounded-xl p-3">
                  <div v-html="iconsApp.mensuality2" />
                </div>
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">Cuota mes: {{quotaData.month_label}}</h1>
              <div>
              <q-chip :color="quotaData.status_color" text-color="white" class="text-weight-bold">
                {{ quotaData.status_label }}
              </q-chip>
              </div>
            </div>
            <div class="col-md-3 col-6 text-right">
              <div class="flex justify-end md:pb-1">
                <div class="p-4  dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creada el:</span> {{ moment(quotaData.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
              <div class="row ">
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-12">
                  <div class="text-grey-7 font-medium text-md">N° de departemento :</div>
                  <div class="text-primary text-md font-bold" style="text-transform: uppercase;">{{ quotaData.departament?.number }}</div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-12">
                  <div class="text-grey-7 font-medium text-md">Propietario:</div>
                  <div class="text-primary text-md font-bold">{{ quotaData.departament?.owner?.name ?? '—' }} </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-12">
                  <div class="text-grey-7 font-medium text-md">Responsable de pago:</div>
                  <div class="text-primary text-md font-bold">{{ quotaData.responsible_pivot?.user?.name ?? quotaData.departament?.owner?.name }}</div>
                </div>
                
              </div>
            </div>
          </div>
          <!-- Desglose de la cuota - Consolidado -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
              {{ hasConsolidation ? 'Desglose consolidado' : 'Desglose de la cuota' }}
            </h3>

            <!-- Mantenimiento -->
            <div class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Mantenimiento</h4>
              <div v-if="maintenanceBreakdown" class="text-xs text-gray-400 mb-2">
                Presupuesto total: S/. {{ maintenanceBreakdown.maintenance_budget_total }}
              </div>
              <div v-for="q in allQuotasForBreakdown" :key="'mnt-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                    class="text-blue-600 hover:underline font-medium" :class="{ 'font-bold': q.isCurrent }">
                    {{ q.number }}
                  </router-link>
                  <span v-if="q.isCurrent"
                    class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold">
                    ESTA
                  </span>
                </div>
                <span class="font-medium text-gray-800">S/. {{ q.maintenance_amount.toFixed(2) }}</span>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal mantenimiento</span>
                <span class="text-primary">S/. {{ totalMaintenance.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Consumo de agua -->
            <div v-if="allQuotasForBreakdown.some(q => q.has_water_reading)" class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Consumo de agua</h4>
              <div v-for="q in allQuotasForBreakdown.filter(q => q.has_water_reading)" :key="'water-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                    class="text-blue-600 hover:underline font-medium" :class="{ 'font-bold': q.isCurrent }">
                    {{ q.number }}
                  </router-link>
                  <span v-if="q.waterReading" class="text-xs text-gray-500">
                    {{ q.waterReading.previous_reading }} → {{ q.waterReading.current_reading }} m³
                  </span>
                </div>
                <router-link :to="{ name: 'quotaWaterDetailClient', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium">
                  S/. {{ q.water_amount.toFixed(2) }}
                </router-link>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal agua</span>
                <span class="text-primary">S/. {{ totalWater.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Agua sin lectura (only for current quota if no consolidated) -->
            <div v-else-if="quotaData.water_amount > 0" class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Agua</h4>
              <div class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <span class="font-medium text-gray-800">Sin lectura individual</span>
                <span class="font-medium text-gray-800">S/. {{ quotaData.water_amount.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Cargos extras -->
            <div v-if="allQuotasForBreakdown.some(q => q.extra_amount > 0)" class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Cargos extras</h4>
              <div v-for="q in allQuotasForBreakdown.filter(q => q.extra_amount > 0)" :key="'extra-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium">
                  {{ q.number }}
                </router-link>
                <span class="font-medium text-orange-700">S/. {{ q.extra_amount.toFixed(2) }}</span>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal extras</span>
                <span class="text-orange-700">S/. {{ totalExtras.toFixed(2) }}</span>
              </div>
            </div>

            <!-- Total -->
            <div v-if="hasConsolidation"
              class="flex justify-between items-center py-3 text-base font-bold border-t-2 border-gray-200">
              <span class="text-gray-900">Total consolidado</span>
              <span class="text-primary text-lg">S/. {{ quotaData.amount }}</span>
            </div>
          </div>

          <!-- Datos del pago -->
          <div v-if="payData" class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Datos del pago</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado</span>
                <span class="font-semibold" :class="'text-' + quotaData.status_color">{{ quotaData.status_label }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha de pago</span>
                <span class="text-gray-900 font-semibold">{{ moment(payData.pay_date).format('DD/MM/YYYY') ?? '—' }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Método de pago</span>
                <span class="text-gray-900 font-semibold">{{ payData.pay_method?.name ?? 'S/N' }}</span>
              </div>
              <div v-if="payData.credit_applied > 0" class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Saldo a favor aplicado</span>
                <span class="font-semibold text-green-600">S/. {{ Number(payData.credit_applied).toFixed(2) }}</span>
              </div>
              <div v-if="hasConsolidation" class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Cuotas consolidadas</span>
                <span class="text-gray-900 font-semibold">{{ allQuotasForBreakdown.length }} cuotas</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">N° de cuota</span>
                <span class="text-gray-900 font-semibold">#{{ quotaData.number }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Nro. de operación</span>
                <span class="text-gray-900 font-semibold">#{{ payData.reference ?? '—' }}</span>
              </div>
            </div>
            <div class="flex flex-center mt-4" @click="showVoucherModal = true">
              <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher" style="text-decoration:dotted">
                Voucher de pago
              </div>
              <span class="ml-2" v-html="iconsApp.voucher"></span>
            </div>
          </div>
          <!-- Botones de acción -->    
          <div class="w-full space-y-4">
            <button @click="downloadReceipt" v-if="quotaData.status === 3"
              class="w-full py-4 border border-gray-300 rounded-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
              </svg>
              <span>Descargar Recibo</span>
            </button>
          </div>
        </div>
        <template v-if="payData">
          <voucherModal :vaucher="payData.vaucher" :dialog="showVoucherModal" @closeModal="showVoucherModal = false" />
        </template>

      </div>

      <!-- No Quota Found -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Cuota no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La cuota solicitada no existe o no tienes permisos para verla.</p>
        <button @click="goToHome"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver al inicio
        </button>
      </div>
    </div>
  </div>
</template>
<style lang="scss">
.dateFact{
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}
</style>