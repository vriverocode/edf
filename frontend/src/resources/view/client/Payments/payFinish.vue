<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import notificationSound from '@/assets/audio/audio_1.mp3'
import moment from 'moment'
import { usePayStore } from '@/services/store/pay.store'
const route = useRoute()
const router = useRouter()
const payStore = usePayStore()
const sound = new Audio(notificationSound)

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
const downloadReceipt = async () => {
  const payId = pay.value?.id
  if (!payId) return
  const token = localStorage.getItem('access_token')
  const baseUrl = (import.meta.env.VITE_LARAVEL_API_URL || '').replace(/\/+$/, '')
  try {
    const res = await fetch(baseUrl + '/api/pays/receipt/' + payId, {
      headers: { Authorization: 'Bearer ' + token }
    })
    if (!res.ok) {
      let message = 'Error al descargar el recibo'
      try {
        const err = await res.json()
        message = err?.error || err?.message || message
      } catch (e) {}
      Notify.create({ color: 'negative', message })
      return
    }
    const contentType = res.headers.get('Content-Type') || ''
    if (!contentType.includes('application/pdf')) {
      Notify.create({ color: 'negative', message: 'El recibo no está disponible' })
      return
    }
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'recibo-pago-' + (pay.value?.pay_id || payId) + '.pdf'
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (e) {
    console.error('Error al descargar recibo:', e)
    Notify.create({ color: 'negative', message: 'Error al descargar el recibo' })
  }
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

onMounted(() => {
  setTimeout(() => {
    sound.play()
  }, 2000)
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
        <div class="mb-4">
          <div class="main-container">
            <div class="check-container">
              <div class="check-background">
                <svg viewBox="0 0 65 51" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M7 25L27.3077 44L58.5 7" stroke="white" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
              </div>
              <div class="check-shadow"></div>
            </div>
          </div>
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
              <span class="text-gray-900 font-semibold">#{{ pay.reference }}</span>
            </div>
            <!-- Monto pagado -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Monto pagado</span>
              <span class="text-gray-900 font-semibold">S/. {{ Number(pay.amount).toFixed(2) }}</span>
            </div>

            <!-- Fecha de pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Fecha de pago</span>
              <span class="text-gray-900 font-semibold">{{ moment(pay.pay_date).format('DD/MM/YYYY') }}</span>
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
          <button @click="downloadReceipt"
            class="w-full py-4 border border-gray-300 rounded-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
              </path>
            </svg>
            <span>Descargar Recibo</span>
          </button>

          <!-- Enlace de volver al inicio -->
          <div class="text-center pt-5">
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

<style lang="scss">
.main-container {
	width: 100%;
	height: 100vh;
	display: flex;
	flex-flow: column;
	justify-content: center;
	align-items: center;
	animation: animateTotal 0.75s ease-out forwards 2s;

}

.check-container {
	width: 7.5rem;
	height: 8.5rem;
	display: flex;
	flex-flow: column;
	align-items: center;
	justify-content: space-between;

	.check-background {
		width: 100%;
		height: calc(100% - 1.25rem);
		background: $primary;
		box-shadow: 0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset;
		transform: scale(0.84);
		border-radius: 50%;
		animation: animateContainer 0.75s ease-out forwards 0.75s;
		display: flex;
		align-items: center;
		justify-content: center;
		opacity: 0;

		svg {
			width: 65%;
			transform: translateY(0.25rem);
			stroke-dasharray: 80;
			stroke-dashoffset: 80;
			animation: animateCheck 0.35s forwards 1.25s ease-out;
		}
	}

	.check-shadow {
		bottom: calc(-15% - 5px);
		left: 0;
		border-radius: 50%;
		background: radial-gradient(closest-side, rgb(32, 44, 95), transparent);
		animation: animateShadow 0.75s ease-out forwards 0.75s;
	}
}

@keyframes animateContainer {
	0% {
		opacity: 0;
		transform: scale(0);
		box-shadow: 0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset;
	}
	25% {
		opacity: 1;
		transform: scale(0.9);
		box-shadow: 0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset;
	}
	43.75% {
		transform: scale(1.15);
		box-shadow: 0px 0px 0px 43.334px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 65px rgba(255, 255, 255, 0.25) inset;
	}
	62.5% {
		transform: scale(1);
		box-shadow: 0px 0px 0px 0px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 21.667px rgba(255, 255, 255, 0.25) inset;
	}
	81.25% {
		box-shadow: 0px 0px 0px 0px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 0px rgba(255, 255, 255, 0.25) inset;
	}
	100% {
		opacity: 1;
		box-shadow: 0px 0px 0px 0px rgba(255, 255, 255, 0.25) inset,
			0px 0px 0px 0px rgba(255, 255, 255, 0.25) inset;
	}
}

@keyframes animateCheck {
	from {
		stroke-dashoffset: 80;
	}
	to {
		stroke-dashoffset: 0;
	}
}

@keyframes animateShadow {
	0% {
		opacity: 0;
		width: 100%;
		height: 15%;
	}
	25% {
		opacity: 0.25;
	}
	43.75% {
		width: 40%;
		height: 7%;
		opacity: 0.35;
	}
	100% {
		width: 85%;
		height: 15%;
		opacity: 0.25;
	}
}

@keyframes animateTotal {
	0% {
		height: 100vh;
	}
	100% {
	  height: 20vh;
	}
}
</style>