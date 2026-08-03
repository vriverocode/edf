<script setup>
import { useAuthStore } from '@//services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import eventos from '@/assets/img/menu/eventos3.png'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
    '_'
  ),
})
const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const emit = defineEmits(['selectEvent'])
const props = defineProps({
  events: {
    type: Array,
    default: []
  },
})
const goTo = (url) => {
  router.push(url);
}
const formatLocation = (event) => {
  let location = event?.booking?.comun_area.name || event.location

  return location || '---'
}

const selectEvent = (eventId) => {
  emit('selectEvent', eventId)
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`;
  }
  return 'Gratis';
}

</script>
<template>
  <!-- Lista de reservas -->
  <div v-if="events.length > 0" class="space-y-3 md:px-5">
    <div v-for="event in events" :key="event.id"
      class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5" style="position: relative;">

      <!-- Sección superior - Detalles de la reserva -->
      <div class="md:px-6 md:pt-4 pb-2 px-3 pt-2  border-gray-300">
        <!-- Header con nombre y estado -->
        <div class="flex justify-between items-center mb-1">
          <div class="ellipsis" style="width: 90%;"   @click="goTo('/client/events/view/' + event.id)">
            <h3 class="text-lg font-bold text-gray-900 mb-1 cursor-pointer">
              {{ event.title || 'Evento' }}
            </h3>
          </div>
          <div class="" style="width: 10%;">
            <div class="ml-3 cursor-pointer md:flex md:justify-end">
              <div v-html="iconsApp.optionsBook" />
              <q-menu>
                <q-list style="min-width: 150px">
                  <q-item clickable v-close-popup @click="goTo('/client/events/view/' + event.id)">
                    <q-item-section>Ver detalles</q-item-section>
                  </q-item>
                  <template v-if="user.rol_id == 1">
                    <q-item clickable v-close-popup @click="goTo('/admin/events/form/update/' + event.id)">
                      <q-item-section>Editar evento</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item clickable v-close-popup @click="selectEvent(event.id)" data-dialog="cancel"
                      :data-event="event.id">
                      <q-item-section>Eliminar evento</q-item-section>
                    </q-item>
                  </template>
                </q-list>

              </q-menu>
            </div>
          </div>
        </div>

        <!-- Contenido principal con imagen y detalles -->
        <div class="flex items-center space-x-4"  @click="goTo('/client/events/view/' + event.id)">
          <!-- Imagen del área -->
          <div class="w-16 h-16 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <img :src="eventos" class="md:w-auto h-4/5"/>
          </div>

          <!-- Detalles de la reserva -->
          <div class="flex-1 space-y-2">
            <!-- Fechas -->
            <div class="flex items-center text-sm text-gray-700">
              <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                </path>
              </svg>
              <span class="font-medium">{{ moment(event.date).format('DD MMM YYYY') }}</span>
            </div>

            <!-- Horario -->
            <div class="flex items-center text-sm text-gray-700">
              <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="font-medium">
                {{ event.time_from }} - {{ event.time_to }}
              </span>
            </div>
            <div class="flex items-center text-sm text-gray-700">
              <div v-html="iconsApp.location" />
              <span class="font-medium">
                {{ formatLocation(event) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Sección inferior - Estado de pago -->
      <div class="md:px-4 md:py-4 md:pb-0 pb-3 px-3 bg-gray-50">
        <div class="flex justify-end items-center">

        </div>
      </div>
    </div>
  </div>

  <!-- Estado vacío -->
  <div v-else class="flex flex-col items-center justify-center py-20">
      <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
        <img :src="eventos" class="md:w-auto h-3/5"/>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay eventos programados</h3>
      <p class="text-gray-600 text-center mb-6">Aquí encontrarás los próximos eventos y actividades organizados para los residentes del edificio.</p>

  </div>
</template>
<style scoped>
/* Estilos adicionales si es necesario */
.badgeEvents {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>