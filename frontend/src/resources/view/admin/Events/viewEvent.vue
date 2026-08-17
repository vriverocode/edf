<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useEventStore } from '@/services/store/event.store'
import iconsApp from '@/assets/icons/index'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const eventStore = useEventStore()

const eventData = ref(null)
const loading = ref(false)
const error = ref(null)

const eventId = route.params.id || route.query.id

const getEventById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await eventStore.getEventById(id)
    eventData.value = response.data
  } catch (err) {
    console.error('Error al obtener el evento:', err)
    error.value = err || 'Error al cargar el evento'
  } finally {
    loading.value = false
  }
}

const reloadEvent = () => {
  if (eventId) {
    getEventById(eventId)
  }
}

const goToEventsList = () => {
  router.go(-1)
}

const formatLocation = (event) => {
  if (!event) return '---'
  const location = event.booking?.comun_area?.name || event.location
  return location || '---'
}

const goTo = (url) => {
  router.push(url)
}
onMounted(() => {
  if (eventId) {
    getEventById(eventId)
  } else {
    error.value = 'ID de evento no proporcionado'
  }
})
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-0 md:px-6 px-3 h-full" style="overflow: auto;">
      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-4">Cargando evento...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <button
          @click="reloadEvent"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors"
        >
          Reintentar
        </button>
      </div>

      <!-- Evento cargado -->
      <div v-else-if="eventData" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full">
          <div class="row w-full mb-3 items-start">
            <div class="col-12 text-right  ">
              <div class="flex justify-end md:pb-1">
                <div class="px-4 py-2 dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creado el:</span>
                  {{ moment(eventData.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
            </div>
            <div class="flex  items-center col-12 px-2  pt-3 flex-nowrap">
              <div class="">
                <div class="bg-primary rounded-xl p-4">
                  <div v-html="iconsApp['events']" />
                </div>
              </div>
              <div class="">
                <h1 class="text-xl font-bold text-gray-900 md:mb-2 pl-3">
                  {{ eventData.title || 'Evento' }}
                </h1>
                <p class="text-gray-600 mt-1 pl-3">
                  {{ eventData.description || 'Sin descripción registrada' }}
                </p>
              </div>
            </div>
            
          </div>

          <!-- Detalles -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);"
              >
                <span class="text-gray-600 font-medium">Fecha</span>
                <span class="text-gray-900 font-semibold">
                  {{ moment(eventData.date).format('DD/MM/YYYY') }}
                </span>
              </div>
              <!-- Horario -->
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);"
              >
                <span class="text-gray-600 font-medium">Horario</span>
                <span class="text-gray-900 font-semibold">
                  {{ eventData.time_from }} - {{ eventData.time_to }}
                </span>
              </div>

              <!-- Ubicación -->
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);"
              >
                <span class="text-gray-600 font-medium">Ubicación</span>
                <span class="text-gray-900 font-semibold">
                  {{ formatLocation(eventData) }}
                </span>
              </div>
              <!-- Datos de reserva asociada -->
              <div class="flex justify-between items-center pb-1" v-if="eventData.booking">
                <span class="text-gray-600 font-medium">Reserva asociada</span>
                <span @click="" class="text-gray-900 font-semibold cursor-pointer" style="text-decoration: underline;">
                  #{{ eventData.booking.booking_number }}
                </span>
              </div>
               <!-- Asistentes -->
               <div
                class="flex justify-between items-center pb-2"
                v-if="(eventData.assits ?? []).length > 0 || (eventData.not_assits ?? []).length > 0"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);"
              >
                <span class="text-gray-600 font-medium">Asistencia</span>
                <span class="text-gray-900 font-semibold">
                  {{ (eventData.assits ?? []).length}} asistirán
                  <span v-if="(eventData.not_assits ?? []).length > 0" class="text-gray-600 font-semibold">
                    / {{ (eventData.not_assits ?? []).length}} no asistirán
                  </span>
                </span>
              </div>

              <div class="mt-5 w-full ">
                <q-btn label="Ver lista de asistente" color="primary" class="w-full" unelevated  no-caps @click="goTo('/admin/events/attendance/' + eventData.id)" />
              </div>
            </div>
          </div>

          <!-- Botones -->
          <!-- <div class="w-full px-5 pb-5">
            <button
              @click="goToEventsList"
              class="w-full flex flex-center py-3 bg-primary text-white rounded-xl font-medium hover:bg-gray-600 transition-colors"
              
            >
            <q-icon name="eva-bell-outline" class="mx-2" size="1.2rem" />
              Enviar recordatorio
            </button>
          </div> -->
        </div>
      </div>

      <!-- No encontrado -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Evento no encontrado</h2>
        <p class="text-gray-600 text-center mb-6">
          El evento solicitado no existe o no tienes permisos para verlo.
        </p>
        <button
          @click="goToEventsList"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors"
        >
          Volver al listado
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.dateFact {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}
</style>

