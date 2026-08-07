<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useReserveStore } from '@/services/store/reserve.store'
import iconsApp from '@/assets/icons/index';
import moment from 'moment';
import voucherModal from '@/components/pay/voucherModal.vue';


const route = useRoute()
const router = useRouter()
const reserveStore = useReserveStore()

// Estados reactivos
const booking = ref(null)
const loading = ref(false)
const error = ref(null)
const dialog = ref(false)
const refundVoucherDialog = ref(null)
const refundDestLabel = (r) => {
  const snap = r.bank_account_snapshot
  if (!snap) return ''
  try {
    const data = typeof snap.data === 'string' ? JSON.parse(snap.data) : (snap.data || {})
    if (data.type === 'yape') {
      return `A su Yape ${data.yape_name || ''} (${data.yape_phone || ''})`
    }
    return `A su cuenta ${data.holder_name || snap.name || ''}${data.account_number ? ' — N° ' + data.account_number : ''}`
  } catch {
    return snap.name || ''
  }
}
// Función para obtener booking por ID
const getBookingById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await reserveStore.getReserveById(id)
    booking.value = response.data

  } catch (err) {
    console.error('Error al obtener la reserva:', err)
    error.value = err || 'Error al cargar la reserva'
  } finally {

    loading.value = false
  }
}

// Función para descargar recibo
const downloadReceipt = async () => {
  const payId = booking.value?.pay?.id
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
    a.download = 'recibo-reserva-' + (booking.value?.booking_number || payId) + '.pdf'
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
  router.push('/client/reserves/list')
}

// Obtener el ID del booking desde la URL o props
const bookingId = route.params.id || route.query.id

// Cargar el booking al montar el componente
onMounted(() => {
  if (bookingId) {
    getBookingById(bookingId)
  } else {
    error.value = 'ID de reserva no proporcionado'
  }
})
const mediaUrl = import.meta.env.VITE_LARAVEL_MEDIA_URL

// Función para recargar el booking
const reloadBooking = () => {
  if (bookingId) {
    getBookingById(bookingId)
  }
}
</script>

<template>
  <div class="h-full  relative " style="overflow: auto;">
    <div class="relative  pt-8 pb-8 md:px-6 px-3">
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
      <div v-else-if="booking" class="flex flex-col items-center md:px-28 md:mx-28 pb-8 ">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full ">
          <div class="row w-full mb-3 items-end">
            <div class="flex flex-col items-start col-md-6 col-6 md:pl-5 pl-3 ">
              <div class="mb-4 pt-5 w-3/5">
                <div class="bg-primary rounded-xl p-4 boxContentV2">
                  <div class="boxItem_v2-2  md:px-6 ">
                    <div class="flex justify-center items-center h-full w-full ">
                      <img :src="mediaUrl + '/images/icons/' + (booking.comun_area?.icon || 'default') " alt=""
                        style="height:100%">
                    </div>
                  </div>
                </div>
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">
                {{ booking.comun_area?.name || 'Área Común' }}
              </h1>
            </div>
            <div class="col-md-6 col-6 text-right">
              <div class="flex justify-end">
                <div class="p-4  dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creada el:</span> {{
                    moment(booking.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
              <div class="mt-4 md:pr-5 pr-3">
                <div class="text-grey-7 font-medium text-md">A nombre:</div>
                <div class="text-primary text-md font-bold">{{ booking.user.name }}</div>
              </div>
              <div class="mt-4 md:pr-5 pr-3">
                <div class="text-grey-7 font-medium text-md">Contacto:</div>
                <div class="text-primary text-md font-bold">{{ booking.user.phone ?? '' }}</div>
              </div>
              <div class="mt-4 md:pr-5 pr-3">
                <div class="text-grey-7 font-medium text-md">Unidad:</div>
                <div class="text-primary text-md font-bold">{{ booking.departament?.number ? '#' + booking.departament.number : '-' }}</div>
              </div>
            </div>
          </div>
          <!-- Tarjeta de detalles -->
          <div class=" w-full  md:p-5  px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              <!-- Estado del pago -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado de la reserva</span>
                <span class="font-semibold" :class="'text-' + booking.status_color">{{ booking.status_label }}</span>
              </div>

              <!-- Monto pagado -->
              <div class="flex justify-between items-center pb-2" v-if="booking.amount > 0"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Monto pagado</span>
                <span class="text-gray-900 font-semibold">S/. {{ booking.amount }}</span>
              </div>

              <!-- Fecha de pago -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha de reserva</span>
                <span class="text-gray-900 font-semibold">{{ moment(booking.date).format('DD/MM/YYYY') }}</span>
              </div>

              <!-- Método de pago -->
              <div class="flex justify-between items-center pb-2" v-if="booking.amount > 0 && booking.pay"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Método de pago</span>
                <span class="text-gray-900 font-semibold">
                  {{ booking.pay?.pay_method?.name || 'S/N' }}
                </span>
              </div>

              <!-- ID de transacción -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">ID de reserva</span>
                <span class="text-gray-900 font-semibold">#{{ booking.booking_number }}</span>
              </div>
              <!-- Horarios -->
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Horario</span>
                <span class="text-gray-900 font-semibold">{{ booking.time_from }} - {{ booking.time_to }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Tipo de reserva</span>
                <span class="text-gray-900 font-semibold">
                  <q-chip :color="booking.type_color" size="0.8rem">
                    <div class="text-white" style="font-weight:600; font-size:0.8rem">
                      {{ booking.type_label }}
                    </div>
                  </q-chip>
                </span>
              </div>



            </div>
            <div class="flex flex-center mt-4" @click="dialog = true" v-if="booking.pay">
              <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher"
                style="text-decoration:dotted">
                Voucher de pago
              </div>
              <span class="ml-2" v-html="iconsApp.voucher"></span>
            </div>

            <!-- Devoluciones -->
            <div v-if="booking.refunds && booking.refunds.length > 0" class="q-mt-md"
              style="border-top: 1px solid rgba(211, 211, 211, 0.534);">
              <div class="text-subtitle1 text-bold text-black q-mt-sm q-mb-sm">Devolución</div>
              <div v-for="r in booking.refunds" :key="r.id" class="flex justify-between items-center py-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <div>
                  <div class="text-gray-600 font-medium">{{ r.kind === 'warranty' ? 'Garantía' : 'Cancelación' }}</div>
                  <div class="text-gray-900 font-semibold">S/. {{ r.amount }}</div>
                  <div class="text-caption text-grey-7">{{ refundDestLabel(r) }}</div>
                </div>
                <div class="flex flex-center cursor-pointer" @click="refundVoucherDialog = r.vaucher"
                  v-if="r.vaucher">
                  <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher"
                    style="text-decoration:dotted">
                    Voucher de devolución
                  </div>
                  <span class="ml-2" v-html="iconsApp.voucher"></span>
                </div>
              </div>
            </div>
          </div>
          <!-- Botones de acción -->
          <div class="w-full  space-y-4">
          <!-- Botón de descargar recibo -->
          <button @click="downloadReceipt" v-if="booking.pay"
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
        <template v-if="booking.pay">
          <voucherModal :vaucher="booking.pay.vaucher" :dialog="dialog" @closeModal="dialog = false" />
          <voucherModal v-if="refundVoucherDialog" :vaucher="refundVoucherDialog"
            :dialog="!!refundVoucherDialog" @closeModal="refundVoucherDialog = null" />
        </template>

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
        <h2 class="text-xl font-bold text-gray-900 mb-2">Reserva no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La reserva solicitada no existe o no tienes permisos para verla.</p>
        <button @click="goToHome"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver al inicio
        </button>
      </div>
    </div>
  </div>
</template>
<style lang="scss">
.dateFact {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}

.boxContentV2 {
  background: $primary;
  overflow: hidden;
  position: relative;
  height: 8.1rem;
  width: 100%;
}

.boxItem_v2-2 {
  border-radius: 0.8rem;
  overflow: visible;
  position: relative;
  // border: 2px solid rgb(3, 156, 195) ;
  width: 100%;
  //box-shadow: 0px 0.1rem 1rem 0px rgba(0, 0, 0, 0.205);
  background-repeat: no-repeat;
  background-size: cover;

  transition: all 0.7s ease-in-out;
  cursor: pointer;
  height: 100%;

  &:hover {
    transform: scale(1.03);
  }
}

@media (max-width: 780px) {

  .boxContentV2 {
    height: 6.5rem;
  }
}
</style>