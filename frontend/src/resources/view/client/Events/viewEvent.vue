<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useEventStore } from '@/services/store/event.store'
import iconsApp from '@/assets/icons/index'
import moment from 'moment'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@//services/store/auth.services'

const route = useRoute()
const router = useRouter()
const eventStore = useEventStore()
const { user } = storeToRefs(useAuthStore())
const eventData = ref(null)
const loading = ref(false)
const loadingButton = ref(false)
const error = ref(null)
const confirmAssits = ref(true);
const eventId = route.params.id || route.query.id
const assistVote = ref({})
const getEventById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await eventStore.getEventById(id)
    eventData.value = response.data
    yetAssist()
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
  router.push('/client/events')
}

const formatLocation = (event) => {
  if (!event) return '---'
  const location = event.booking?.comun_area?.name || event.location
  return location || '---'
}

const setAssist = (type) => {
  loadingButton.value = true;
  const data = {
    assitType: type 
  }
  eventStore.setAssitByData(eventData.value.id, data)
  .then((response) => {
    console.error(response)
    eventData.value.assits = response.data.assits
    eventData.value.not_assits = response.data.not_assits

    yetAssist()
  })
  .catch((response) => {
    console.error(response)
  })
  .finally(() => {
    loadingButton.value = false;
  })
}
const yetAssist = () => {
  
 let assits = JSON.parse(eventData.value.assits ?? '[]')
 let notAssits = JSON.parse(eventData.value.not_assits ?? '[]')

 console.error(assits)
 console.error(notAssits)
 
  if(assits.includes(user.value.id) || notAssits.includes(user.value.id)){
    confirmAssits.value = false 
  }
  youAssistVote()
}
const youAssistVote = () => {
  let assits = JSON.parse(eventData.value.assits ?? '[]')
  let notAssits = JSON.parse(eventData.value.not_assits ?? '[]')
 
  if(assits.includes(user.value.id)){
    assistVote.value = {
      title: 'Haz marcado que asistiras',
      icon: 'eva-checkmark-outline',
      color: 'positive'
    }
  }
  else{
    assistVote.value = {
      title: 'Haz marcado que no asistiras',
      icon: 'eva-close-outline',
      color: 'negative'
    }
  }
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
    <div class="relative pt-8 pb-10 md:px-6 px-3 h-full" style="overflow: auto;">
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
      <div v-else-if="eventData" class="flex flex-col items-center md:px-28 md:mx-28" >
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
            </div>
          </div>

          <!-- Botones -->
          <div class="w-full pb-5 row">
            <template v-if="confirmAssits">
              <div class="col-12 col-md-6 mt-3 md:mt-0 px-5">
                <q-btn
                  @click="setAssist(0)"
                  class="w-full flex flex-center py-3"
                  outline
                  color="primary"
                  no-caps
                  style="border-radius: 0.8rem;"
                  :loading="loadingButton"
                >
                  <q-icon name="eva-bell-outline" class="mx-2" size="1.2rem" />
                  <div>
                    No voy a asistir
                  </div>
                </q-btn>
              </div>
              <div class="col-12 col-md-6 mt-5 md:mt-0 px-5">
                <q-btn
                  @click="setAssist(1)"
                  class="w-full flex flex-center py-3" 
                  unelevated
                  color="primary"
                  no-caps
                  style="border-radius: 0.8rem;"
                  :loading="loadingButton"
                >
                  <q-icon name="eva-bell-outline" class="mx-2" size="1.2rem" />
                  <div>
                    Voy a asistir
                  </div>
                </q-btn>
              </div>
            </template>
            <template v-else>
              <div class="flex flex-center col-12">
                <div class="flex flex-center py-3 px-14 assistVote" :class="`b-${assistVote.color}`">
                  <q-icon :name="assistVote.icon" :color="assistVote.color" size="1.5rem" class="text-bold"/>
                  <div :class="'text-'+assistVote.color" style="font-size: 1rem;" class="text-bold ml-2">{{assistVote.title}}</div>
                </div>
              </div>
            </template>
          </div>
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

<style lang="scss">
.assistVote {
  border: 1px solid;
  border-radius: 0.7rem;
}
.b-positive{
  border-color: $positive;
}
.b-negative{
  border-color: $negative;
}
.dateFact {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}
</style>

