<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useEventStore } from '@/services/store/event.store'
import moment from 'moment'
import eventos from '@/assets/img/menu/eventos3.png'

const route = useRoute()
const router = useRouter()
const eventStore = useEventStore()
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL 
const eventData = ref(null)
const assits = ref([])
const notAssits = ref([])
const loading = ref(false)
const error = ref(null)
const tab = ref('assits')

const eventId = route.params.id || route.query.id

const getAttendance = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await eventStore.getEventAttendance(id)
    eventData.value = response.data.event
    assits.value = response.data.assits
    notAssits.value = response.data.not_assits
  } catch (err) {
    console.error('Error al obtener la asistencia:', err)
    error.value = err || 'Error al cargar la asistencia'
  } finally {
    loading.value = false
  }
}

const reload = () => {
  if (eventId) {
    getAttendance(eventId)
  }
}

const goToEventsList = () => {
  router.push('/admin/events')
}

const formatLocation = (event) => {
  if (!event) return '---'
  const location = event.booking?.comun_area?.name || event.location
  return location || '---'
}

const getInitials = (name) => {
  const parts = String(name || '').trim().split(/\s+/)
  return (parts[0]?.[0] || '') + (parts[1]?.[0] || '')
}

const getUserDepartments = (user) => {
  console.log(user)
  return user.departments[0].number
}

onMounted(() => {
  if (eventId) {
    getAttendance(eventId)
  } else {
    error.value = 'ID de evento no proporcionado'
  }
})
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-0 pb-10 md:px-6 px-0 h-full" style="overflow: auto;">
      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-4">Cargando asistencia...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-5">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <button
          @click="reload"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors"
        >
          Reintentar
        </button>
      </div>

      <!-- Asistencia cargada -->
      <div v-else-if="eventData" class="flex flex-col items-center md:px-28">
        <div class="bg-white flex flex-col w-full ">
          <div class="row w-full mb-3 items-start ">
            <div class="col-12 text-right">
              <div class="flex justify-end md:pb-1">
                <div class="px-4 py-2 dateFactAtt text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Fecha:</span>
                  {{ moment(eventData.date).format('DD/MM/YYYY') }}
                </div>
              </div>
            </div>
            <div class="flex items-center col-12 px-4 pt-3 flex-nowrap">
              <div class="cursor-pointer" style="height: 70px; width: 70px;">
                <div class="bg-primary rounded-xl ">
                  <img 
                  v-if="eventData.booking?.comun_area?.icon"
                  :src="urlMedia + '/images/icons/' + eventData.booking?.comun_area?.icon"
                   alt="" class="md:w-auto h-4/5" >
                  <img v-else :src="eventos" class="md:w-auto h-4/5" />

                </div>    
              </div>
              <div class="">
                <h1 class="text-xl font-bold text-gray-900 md:mb-2 pl-3">
                  {{ eventData.title || 'Evento' }}
                </h1>
                <p class="text-gray-600 mt-1 pl-3">{{ formatLocation(eventData  ) }}</p>
              </div>
            </div>
          </div>

          <!-- Tabs de asistencia -->
          <q-tabs
            v-model="tab"
            dense
            class="text-primary py-2 pt-1"
            style="border-top: 1px solid lightgray;"
            align="justify"
          >
            <q-tab name="assits" no-caps>
              <div class="flex items-center gap-2">
                <q-icon name="eva-checkmark-outline" size="1.2rem" color="positive" />
                Asisten ({{ assits.length }})
              </div>
            </q-tab>
            <q-tab name="not_assits" no-caps>
              <div class="flex items-center gap-2">
                <q-icon name="eva-close-outline" size="1.2rem" color="negative" />
                No asisten ({{ notAssits.length }})
              </div>
            </q-tab>
          </q-tabs>

          <q-tab-panels v-model="tab" animated class="bg-transparent">
            <!-- Asisten -->
            <q-tab-panel name="assits" class="px-4 py-4">
              <div v-if="assits.length > 0" class="space-y-3">
                <div
                  v-for="user in assits"
                  :key="user.id"
                  class="bg-white rounded-xl shadow-md border border-gray-100 p-4 flex items-center space-x-4"
                >
                  <div
                    class="w-12 h-12 rounded-full text-positive flex items-center justify-center font-bold text-lg flex-shrink-0" style="background-color: #04e20c0f;"
                  >
                    {{ getInitials(user.name) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                      <h3 class="text-base font-bold text-gray-900 truncate">{{ user.name }}</h3>
                    </div>
                    <p v-if="user.email" class="text-sm text-gray-600 truncate mt-1">
                      {{ user.email }}
                    </p>
                     <p v-if="user.phone" class="text-sm text-gray-600 truncate mt-1">
                      {{ user.phone }}
                    </p>
                    <p v-if="getUserDepartments(user)" class="text-sm text-gray-700 truncate mt-1 flex items-center" style="text-transform: uppercase;">
                      <q-icon name="eva-home-outline" size="0.9rem" class="mr-1" />
                      {{ getUserDepartments(user) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="flex flex-col items-center justify-center py-12">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                  <q-icon name="eva-people-outline" color="primary" size="2rem" />
                </div>
                <p class="text-gray-600 text-center">Aún no hay personas que confirmaron asistencia</p>
              </div>
            </q-tab-panel>

            <!-- No asisten -->
            <q-tab-panel name="not_assits" class="px-4 py-4">
              <div v-if="notAssits.length > 0" class="space-y-3">
                <div
                  v-for="user in notAssits"
                  :key="user.id"
                  class="bg-white rounded-xl shadow-md border border-gray-100 p-4 flex items-center space-x-4"
                >
                  <div
                    class="w-12 h-12 rounded-full bg-negative/10 text-negative flex items-center justify-center font-bold text-lg flex-shrink-0"
                  >
                    {{ getInitials(user.name) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                      <h3 class="text-base font-bold text-gray-900 truncate">{{ user.name }}</h3>
                    </div>
                    <p v-if="user.email || user.phone" class="text-sm text-gray-600 truncate">
                      {{ [user.email, user.phone].filter(Boolean).join(' · ') }}
                    </p>
                    <p v-if="getUserDepartments(user)" class="text-sm text-gray-700 truncate">
                      <q-icon name="eva-home-outline" size="0.9rem" class="mr-1" />
                      {{ getUserDepartments(user) }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="flex flex-col items-center justify-center py-12">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
                  <q-icon name="eva-people-outline" color="negative" size="2rem" />
                </div>
                <p class="text-gray-600 text-center">Aún no hay personas que confirmaron que no asistirán</p>
              </div>
            </q-tab-panel>
          </q-tab-panels>
        </div>
      </div>

      <!-- No encontrado -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <q-icon name="eva-calendar-outline" color="grey-6" size="2.5rem" />
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
.dateFactAtt {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  border-top: 1px solid $primary;

  width: fit-content;
  border-bottom-left-radius: 1rem;
  border-top-left-radius: 1rem;

}
</style>