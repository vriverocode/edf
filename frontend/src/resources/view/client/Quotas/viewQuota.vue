<script setup>
import { ref, onMounted } from 'vue'
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
// Función para obtener quota por ID
const fetchQuotaById = async (id) => {
  try {
    isLoading.value = true
    errorMessage.value = null

    const response = await quotaStore.getQuotaById(id)
    quotaData.value = response.data

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
          <!-- Desglose de la cuota -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Desglose de la cuota</h3>
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b">
                  <th class="text-left py-2 text-gray-600 font-medium">Concepto</th>
                  <th class="text-right py-2 text-gray-600 font-medium">Monto</th>
                </tr>
              </thead>
              <tbody>
                <!-- Mantenimiento -->
                <tr class="border-b">
                  <td class="py-2">
                    <div class="font-medium text-gray-900">Mantenimiento</div>
                    <div v-if="maintenanceBreakdown" class="text-xs font-bold text-gray-400">
                     % Participación: 
                     {{ parseFloat(quotaData.departament?.participation_percentage).toFixed(6) }} 
                      <span v-if="maintenanceBreakdown.maintenance_budget_total">
                        de S/. {{ maintenanceBreakdown.maintenance_budget_total }}
                      </span>
                    </div>
                  </td>
                  <td class="text-right py-2 font-semibold">S/. {{ quotaData.maintenance_amount }}</td>
                </tr>
                <!-- Agua individual -->
                <tr v-if="waterBreakdown" class="border-b">
                  <td class="py-2">
                    <div class="font-medium text-gray-900">Agua (consumo individual)</div>
                    <div class="text-xs text-gray-500">
                      {{ waterBreakdown.previous_reading }} → {{ waterBreakdown.current_reading }} m³
                      ({{ waterBreakdown.water_consumption_m3 }} m³ × S/. {{ waterBreakdown.water_price_per_m3 }})
                    </div>
                  </td>
                  <td class="text-right py-2 font-semibold">S/. {{ quotaData.water_amount }}</td>
                </tr>
                <!-- Agua sin lectura -->
                <tr v-else-if="quotaData.water_amount > 0" class="border-b">
                  <td class="py-2">
                    <div class="font-medium text-gray-900">Agua</div>
                  </td>
                  <td class="text-right py-2 font-semibold">S/. {{ quotaData.water_amount }}</td>
                </tr>
                <!-- Total -->
                <tr class="font-bold">
                  <td class="py-3 text-gray-900">Total a pagar</td>
                  <td class="text-right py-3 text-primary text-lg">S/. {{ quotaData.amount }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Datos del pago -->
          <div v-if="(quotaData.pays?.length ?? 0) > 0" class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
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
                <span class="text-gray-900 font-semibold">{{ moment(quotaData.pays?.[0]?.pay_date).format('DD/MM/YYYY') ?? '—' }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Método de pago</span>
                <span class="text-gray-900 font-semibold">{{ quotaData.pays?.[0]?.pay_method?.name ?? 'S/N' }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">N° de cuota</span>
                <span class="text-gray-900 font-semibold">#{{ quotaData.number }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Nro. de operación</span>
                <span class="text-gray-900 font-semibold">#{{ quotaData.pays?.[0]?.reference ?? '—' }}</span>
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
        <template v-if="(quotaData.pays?.length ?? 0) > 0">
          <voucherModal :vaucher="quotaData.pays?.[0]?.vaucher"  :dialog="showVoucherModal"  @closeModal="showVoucherModal = false"/>
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