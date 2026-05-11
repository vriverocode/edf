<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import checkIcon from '@/assets/img/util/check.webp'
import { usePayStore } from '@/services/store/pay.store'
const route = useRoute()
const router = useRouter()
const payStore = usePayStore()

// Estados reactivos
const pay = ref(null)
const loading = ref(false)
const error = ref(null)
const iconByStatus = [

]
// Función para obtener pay por ID
const getPayById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await payStore.getPayById(id)
    pay.value = response.data

  } catch (err) {
    console.error('Error al obtener la reserva:', err)
    error.value = err || 'Error al cargar la reserva'
  } finally {

    loading.value = false
  }
}

// Función para descargar recibo
const downloadReceipt = () => {
  // Aquí puedes implementar la lógica para descargar el recibo
  console.log('Descargando recibo para la reserva:', pay.value?.id)
  // Por ejemplo, generar un PDF o abrir una nueva ventana con el recibo
}

// Función para ir al inicio
const goToHome = () => {
  router.go(-3)
}

// Obtener el ID del pay desde la URL o props
const payId = route.params.id || route.query.id

// Cargar el pay al montar el componente
onMounted(() => {
  if (payId) {
    getPayById(payId)
  } else {
    error.value = 'ID de reserva no proporcionado'
  }
})

// Función para recargar el pay
const reloadBooking = () => {
  if (payId) {
    getPayById(payId)
  }
}
</script>

<template>
  <div class="h-full  relative overflow-auto">
    <!-- Fondo decorativo urbano -->
    <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-gray-100 to-transparent opacity-30">
      <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-gray-200 to-transparent">
        <!-- Siluetas de edificios -->
        <div class="flex items-end justify-between px-8 ">
          <div class="w-8 h-12 bg-gray-300 rounded-t"></div>
          <div class="w-6 h-8 bg-gray-300 rounded-t"></div>
          <div class="w-10 h-16 bg-gray-300 rounded-t"></div>
          <div class="w-7 h-10 bg-gray-300 rounded-t"></div>
          <div class="w-5 h-14 bg-gray-300 rounded-t"></div>
          <div class="w-9 h-12 bg-gray-300 rounded-t"></div>
          <div class="w-6 h-9 bg-gray-300 rounded-t"></div>
        </div>
      </div>
    </div>

    <div class="relative z-10 pt-3 pb-2 px-6">
      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />

        <p class="text-gray-600 font-medium">Cargando reserva...</p>
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
        <button @click="reloadBooking"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="pay" class="flex flex-col items-center pb-4">
        <!-- Icono de éxito -->
        <!-- <div class="w-24 h-24 bg-green-500 rounded-full flex items-center justify-center mb-6 shadow-lg">
          <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
          </svg>
        </div> -->
        <div class="mb-4">
          <img :src="checkIcon" alt="" style="width: 5.5rem;">
        </div>

        <!-- Título de éxito -->
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Pago realizado!</h1>
        <p class="text-gray-600 mb-1 text-center">Tu pago ha sido creado exitosamente</p>
        <p class="text-gray-600 mb-5 text-center">El equipo de administración lo validara y se te noficará cuando sea
          confirmado</p>


        <!-- Tarjeta de detalles -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-sm p-6 mb-6">
          <div class="space-y-4">
            <!-- Estado del pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Estado de pago</span>
              <span class="font-semibold" :class="'text-' + pay.status_color">{{ pay.status_label }}</span>
            </div>
            <!-- Horarios -->
            <div class="flex justify-between items-center pb-2" v-if="pay.pay_method != 3"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Refencia de pago</span>
              <span class="text-gray-900 font-semibold">{{ pay.reference }}</span>
            </div>
            <!-- Monto pagado -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Monto pagado</span>
              <span class="text-gray-900 font-semibold">S/. {{ pay.amount }}</span>
            </div>

            <!-- Fecha de pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Fecha de pago</span>
              <span class="text-gray-900 font-semibold">{{ new Date(pay.pay_date).toLocaleDateString('es-ES') }}</span>
            </div>

            <!-- Método de pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Método de pago</span>
              <span class="text-gray-900 font-semibold">
                {{ pay.pay_method.name }}
              </span>
            </div>

            <!-- ID de transacción -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">ID de pago</span>
              <span class="text-gray-900 font-semibold">#{{ pay.pay_id }}</span>
            </div>

            <!-- Área común -->
            <div class="flex justify-between items-center pb-2" v-if="pay.booking"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Reserva</span>
              <span class="text-gray-900 font-semibold">#{{ pay.booking?.booking_number || 'Área Común' }}</span>
            </div>
            <div class="flex justify-between items-center pb-2" v-if="pay.quota"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Cuota del mes</span>
              <span class="text-gray-900 font-semibold">{{ pay.quota?.month_label || '---' }}</span>
            </div>
          </div>
        </div>

        <!-- Botones de acción -->
        <div class="w-full max-w-sm space-y-0">
          <!-- Botón de descargar recibo -->
          <!-- <button @click="downloadReceipt"
            class="w-full py-4 border border-gray-300 rounded-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
              </path>
            </svg>
            <span>Descargar Recibo</span>
          </button> -->

          <!-- Enlace de volver al inicio -->
          <div class="text-center ">
            <button @click="goToHome" class="text-gray-600 font-medium underline hover:text-gray-800 transition-colors">
              Volver al inicio
            </button>
          </div>
        </div>
      </div>

      <!-- No Booking Found -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Pago no encontrado</h2>
        <p class="text-gray-600 text-center mb-6">El pago solicitada no existe o no tienes permisos para verla.</p>
        <button @click="goToHome"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver al inicio
        </button>
      </div>
    </div>
  </div>
</template>