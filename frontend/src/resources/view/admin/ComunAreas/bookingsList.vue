<script setup>
import { useReserveStore } from '@/services/store/reserve.store';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
    '_'
  ),
})

const reserveStore = useReserveStore()
const reserves = ref([])
const readyPage = ref(false)
const router = useRouter()
const route = useRoute()

const getReservesByArea = () => {
  reserveStore.getReservesByArea(route.params.id)
    .then((response) => {
      console.log(response)
      reserves.value = response.data
      setTimeout(() => {
        readyPage.value = true
      }, 100);
    })
    .catch((response) => {
      console.log(response)
    })
}
const goTo = (url) => {
  router.push(url);
}


const formatTime = (time) => {
  return time;
}

const getPaymentStatus = (booking) => {
  if (booking.amount > 0) {
    return !booking.pay
      ? 'No pagada'
      : booking.pay.status == 1
        ? 'Pendiente de aprobación'
        : 'Pagado';
  }
  return 'Confirmado';
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`;
  }
  return 'Gratis';
}
onMounted(() => {
  getReservesByArea()
})

</script>
<template>
  <div class="h-full">

    <div class=" " style="height: 90%; overflow: auto;">

      <!-- Loading State -->
      <div v-if="!readyPage" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />

      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de reservas -->
        <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;">

            <!-- Sección superior - Detalles de la reserva -->
            <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
              <!-- Header con nombre y estado -->
              <div class="flex justify-between items-start mb-2">
                <!-- Estado badge -->
                <span :class="'bg-' + reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <!-- Contenido principal con imagen y detalles -->
              <div class="flex items-center space-x-4">
                <!-- Imagen del área -->
                <!-- <div class="w-16 h-16 bg-gray-200 rounded-xl flex items-center justify-center flex-shrink-0">
                  <div v-html="iconsApp[reserve.comun_area.icon]" />
                </div> -->

                <!-- Detalles de la reserva -->
                <div class="flex-1 space-y-2">
                  <!-- Fechas -->
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>

                  <!-- Horario -->
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTime(reserve.time_from) }} - {{ formatTime(reserve.time_to) }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <div v-html="iconsApp.moneyIcon" />
                    <span class="font-medium">
                      {{ getPaymentAmount(reserve) }} -
                      <span class="font-medium">
                        {{ reserve.pay?.pay_method.name ?? 'Confirmada' }}
                      </span>
                    </span>
                  </div>

                </div>
              </div>
            </div>

            <!-- Sección inferior - Estado de pago -->
            <div class="p-4 bg-gray-50">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-sm font-medium text-gray-700">{{ reserve.user.name }}</span>
                </div>
                <div class="flex items-center">
                  <q-btn unelevated rounded color="warning" size="sm" class="ml-3" v-if="reserve.status == 2"
                    @click="goTo('/admin/pay/validate/' + reserve.pay.id)">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Validar pago
                    </q-tooltip>
                    <div v-html="iconsApp.processPay"></div>
                  </q-btn>
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Información de reserva
                    </q-tooltip>
                    <div v-html="iconsApp.optionsBook"></div>
                    <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="goTo('/client/reserves/view/' + reserve.id)">
                          <q-item-section>Ver detalles</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup v-if="reserve.status == 2"
                          @click="goTo('/admin/pay/validate/' + reserve.pay.id)">
                          <q-item-section>Validar Pago</q-item-section>
                        </q-item>
                        <q-separator />
                      </q-list>
                    </q-menu>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
              </path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No tienes reservas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no has realizado ninguna reserva de áreas comunes.</p>
        </div>
      </div>
    </div>

    <!-- Botón flotante para crear reserva -->
    <div class="px-4 pb-12 md:pb-8 md:flex  md:justify-center items-center md:w-full md:px-12" style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem; width: 100%;" @click="goTo('/client/reserves/form/add')">
        <div class="flex items-center py-2">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Agregar reserva
          </div>
        </div>
      </q-btn>
    </div>
  </div>
</template>
<style scoped>
/* Estilos adicionales si es necesario */
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>