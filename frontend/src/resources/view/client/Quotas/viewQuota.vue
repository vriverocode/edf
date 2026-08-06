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
const quota = ref(null)
const loading = ref(false)
const error = ref(null)
const dialog = ref(false)
// Función para obtener quota por ID
const getQuotaById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await quotaStore.getQuotaById(id)
    quota.value = response.data

  } catch (err) {
    console.error('Error al obtener la reserva:', err)
    error.value = err || 'Error al cargar la cuota'
  } finally {

    loading.value = false
  }
}

// Función para descargar recibo
const downloadReceipt = async () => {
  const quotaId = quota.value?.id
  if (!quotaId) return
  const token = localStorage.getItem('access_token')
  try {
    const res = await fetch('/api/bill-invoices/client-download/' + quotaId, {
      headers: { Authorization: 'Bearer ' + token }
    })
    if (!res.ok) return
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'recibo-cuota-' + quotaId + '.pdf'
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

// Obtener el ID del quota desde la URL o props
const quotaId = route.params.id || route.query.id

// Cargar el quota al montar el componente
onMounted(() => {
  if (quotaId) {
    getQuotaById(quotaId)
  } else {
    error.value = 'ID de reserva no proporcionado'
  }
})

// Función para recargar el quota
const reloadQuota = () => {
  if (quotaId) {
    getQuotaById(quotaId)
  }
}
</script>

<template>
  <div class="min-h-screen  relative overflow-hidden">
    <div class="relative  pt-8 pb-0 md:px-6 px-3">
      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />

        <p class="text-gray-600 font-medium">Cargando cuota...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <button @click="reloadQuota"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="quota" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full ">
          <div class="row w-full mb-3 items-start">
            <div class="flex flex-col items-start col-md-9 col-6 md:pl-5 pl-3 ">
              <div class="mb-4 pt-5">
                <div class="bg-primary rounded-xl p-3">
                  <div v-html="iconsApp.mensuality2" />
                </div>
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">Cuota mes: {{quota.month_label}}</h1>
            </div>
            <div class="col-md-3 col-6 text-right">
              <div class="flex justify-end md:pb-1">
                <div class="p-4  dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creada el:</span> {{ moment(quota.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
              <div class="row ">
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-12">
                  <div class="text-grey-7 font-medium text-md">Apartemento N°:</div>
                  <div class="text-primary text-md font-bold">{{ quota.departament.number }}</div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-12">
                  <div class="text-grey-7 font-medium text-md">Propietario:</div>
                  <div class="text-primary text-md font-bold">{{ quota.departament.owner.name ?? ''}} </div>
                </div>
                
              </div>
            </div>
          </div>
          <!-- Tarjeta de detalles -->
          <div class=" w-full  md:p-5  px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              <!-- Estado del pago -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado de pago</span>
                <span class="font-semibold" :class="'text-' + quota.status_color">{{ quota.status_label }}</span>
              </div>
  
              <!-- Monto pagado -->
              <div class="flex justify-between items-center pb-2"
                v-if="quota.amount > 0"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Monto pagado</span>
                <span class="text-gray-900 font-semibold">S/. {{ quota.amount }}</span>
              </div>
  
              <!-- Fecha de pago -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha de pago</span>
                <span class="text-gray-900 font-semibold">{{ moment(quota.pays[0].pay_date).format('DD/MM/YYYY')}}</span>
              </div>
  
              <!-- Método de pago -->
              <div class="flex justify-between items-center pb-2"
                v-if="quota.amount > 0 &&  quota.pays.length > 0"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Método de pago</span>
                <span class="text-gray-900 font-semibold">
                  {{ quota.pays[0]?.pay_method.name || 'S/N'  }}
                </span>
              </div>
  
              <!-- ID de transacción -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">ID de cuota</span>
                <span class="text-gray-900 font-semibold">#{{ quota.number }}</span>
              </div>
              <!-- N de operación -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Nro. de operación</span>
                <span class="text-gray-900 font-semibold">#{{ quota.pays[0].reference }}</span>
              </div>

            </div>
            <div class="flex flex-center mt-4" @click="dialog = true" v-if="quota.pays.length > 0">
              <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher" style="text-decoration:dotted">
                Voucher de pago 
              </div>
              <span class="ml-2" v-html="iconsApp.voucher"></span>
            </div>
          </div>
          <!-- Botones de acción -->    
          <div class="w-full space-y-4">
            <button @click="downloadReceipt" v-if="quota.status === 3"
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
        <template v-if="quota.pays.length > 0">
          <voucherModal :vaucher="quota.pays[0].vaucher"  :dialog="dialog"  @closeModal="dialog = false"/>
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