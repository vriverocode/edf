<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'
import { Notify } from 'quasar'

const route = useRoute()
const router = useRouter()
const payStore = usePayStore()
const dialog = ref('')

// Estados reactivos
const pay = ref(null)
const loading = ref(false)
const ready = ref(true)

const error = ref(null)

const getPayById = async (id) => {
  try {
    error.value = null
    const response = await payStore.getPayById(id)
    pay.value = response.data

  } catch (err) {
    console.error('Error al obtener la reserva:', err)
    error.value = err || 'Error al cargar la reserva'
  }
  finally{
    ready.value = false
  }
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
const updateStatus = (status) => {
  loading.value = true

  const data = {
    status: status
  }
  payStore.updateStatus({data:data, id:pay.value.id})
  .then((response)  => {
    loading.value = false
    showNotify(response.data.messageType, response.data.message)
    setTimeout(() => {
      router.go(-1)
    }, 1000);
  })
  .catch((response) => {
    loading.value = false
    showNotify(response.messageType, response.message)
  })
}

 const showNotify = (type,text) => {
  Notify.create({
    color:type,
    message: text,
    timeout:2000
  })
}
const showModal = (modal) => {
  dialog.value = modal
}
</script>

<template>
  <div class="h-full  relative overflow-auto">

    <div class="relative z-10 pt-5 pb-2 px-6 h-full">
      <!-- Loading State -->
      <div v-if="ready" class="flex flex-col items-center justify-center py-20">
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
      <div v-else-if="pay" class="flex flex-col items-center h-full" >

        <!-- Tarjeta de detalles -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-sm md:max-w-4xl p-6 pb-4 mb-8">
          <div class="space-y-4">
            <!-- Detalles del pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);" v-if="pay.type == 2 && pay.booking">
              <span class="text-gray-600 font-medium">Area</span>
              <span class="text-gray-900 font-semibold"  >{{ pay.booking.comun_area.name}}</span>
            </div>
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);" v-if="pay.type == 1 && pay.quotas?.length">
              <span class="text-gray-600 font-medium">Cuota(s) pagada(s)</span>
              <span class="text-gray-900 font-semibold flex flex-wrap justify-end gap-1 items-center">
                <span class="bg-primary text-white text-xs px-2 py-1 rounded-md" v-for="quota  in pay.quotas" :key="quota.id">
                  {{ quota.month_label }} (Unidad {{ quota.departament?.number }})
                </span>
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
              <span class="text-gray-900 font-semibold">#{{ pay.booking?.booking_number  || 'Área Común' }}</span>
            </div>
            <!-- Horarios -->
            <div class="flex justify-between items-center pb-2" v-if="pay.pay_method !=3"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Refencia de pago</span>
              <span class="text-gray-900 font-semibold">#{{ pay.reference }}</span>
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
            <!-- User -->
            <div class="flex justify-between items-center pb-2" v-if="pay.user"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">A nombre de</span>
              <span class="text-gray-900 font-semibold">
                {{ pay.user.name }}
              </span>
            </div>
            
          </div>
          <div class="flex flex-center mt-4" @click="showModal('voucher')" v-if="pay.pay_method !=3">
            <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher" style="text-decoration:dotted">
              Vaucher de pago 
            </div>
            <span class="ml-2" v-html="iconsApp.voucher"></span>
          </div>
        </div>
        <div>
          <div class="flex justify-evenly mt-5">
            <q-btn label="Cancelar" unelevated class="q-mx-sm " color="negative" outline
              style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " 
              @click="updateStatus(0)" />

            <q-btn label="Aprobar" unelevated class="q-mx-sm " color="primary" outline
              style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " :loading="loading"
              @click="updateStatus(2)" />
          </div>
        </div>
        <voucherModal :vaucher="pay.vaucher"  :dialog="(dialog == 'voucher')"  @closeModal="dialog = ''"/>
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
.text__vaucher{
  transition: all 0.5s ease;
  &:hover{
    text-decoration: underline;
  }
}

</style>