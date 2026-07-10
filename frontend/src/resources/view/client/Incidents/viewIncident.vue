<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useIncidentStore } from '@/services/store/incident.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const incidentStore = useIncidentStore()

const incident = ref(null)
const loading = ref(false)
const error = ref(null)

const getIncidentById = async (id) => {
  try {
    loading.value = true
    error.value = null
    const response = await incidentStore.getIncidentById(id)
    incident.value = response.data || response
  } catch (err) {
    console.error('Error al obtener la incidencia:', err)
    error.value = err || 'Error al cargar la incidencia'
  } finally {
    loading.value = false
  }
}

const goToHome = () => {
  router.go(-1)
}

const incidentId = route.params.id || route.query.id

onMounted(() => {
  if (incidentId) {
    getIncidentById(incidentId)
  } else {
    error.value = 'ID de incidencia no proporcionado'
  }
})

const reloadIncident = () => {
  if (incidentId) {
    getIncidentById(incidentId)
  }
}
</script>

<template>
  <div class="h-full relative" style="overflow: auto;">
    <div class="relative pt-8 pb-8 md:px-6 px-3">
      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-4">Cargando incidencia...</p>
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
        <button @click="reloadIncident"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="incident" class="flex flex-col items-center md:px-28 md:mx-28 pb-8">
        <div class="bg-white rounded-xl border border-gray-300 flex flex-col items-center w-full">
          <div class="row w-full mb-3 items-start">
            <div class="flex flex-col items-start col-md-6 col-6 md:pl-5 pl-3">
              <div class="mb-4 pt-5">
                <q-icon name="eva-alert-circle-outline" size="4rem" color="primary" />
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">
                {{ incident.title }}
              </h1>
            </div>
            <div class="col-md-6 col-6 text-right">
              <div class="flex justify-end">
                <div class="p-4 dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Reportada el:</span> {{
                    moment(incident.created_at).format('DD/MM/YYYY HH:mm') }}
                </div>
              </div>
            </div>
          </div>
          
          <!-- Tarjeta de detalles -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              <!-- Estado -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado</span>
                <span class="font-semibold" :class="'text-' + (incident.status === 1 ? 'warning' : (incident.status === 4 ? 'positive' : 'info'))">
                  {{ incidentStore.statusLabels[incident.status] }}
                </span>
              </div>

              <!-- Tipo de incidencia -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Tipo de incidencia</span>
                <span class="text-gray-900 font-semibold">
                  <q-chip color="primary" size="0.8rem">
                    <div class="text-white" style="font-weight:600; font-size:0.8rem">
                      {{ incidentStore.typeLabels[incident.type] }}
                    </div>
                  </q-chip>
                </span>
              </div>

              <!-- Fecha de la incidencia -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha de ocurrencia</span>
                <span class="text-gray-900 font-semibold">{{ moment(incident.date).format('DD/MM/YYYY') }}</span>
              </div>

              <!-- Ubicación -->
              <div class="flex justify-between items-center pb-2" v-if="incident.location" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Ubicación</span>
                <span class="text-gray-900 font-semibold text-right" style="max-width: 60%">{{ incident.location }}</span>
              </div>

              <!-- Descripción -->
              <div class="flex justify-between items-center pb-2" v-if="incident.description" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Descripción</span>
                <span class="text-gray-900 font-semibold text-right" style="max-width: 60%">{{ incident.description }}</span>
              </div>

              <!-- Imagen adjunta -->
              <div class="flex flex-col pb-2 mt-4" v-if="incident.images && incident.images.length > 5">
                <span class="text-gray-600 font-medium mb-3">Imagen adjunta</span>
                <div class="flex justify-center border border-gray-200 rounded-lg overflow-hidden p-2 bg-gray-50">
                  <img :src="incident.images" alt="Imagen de la incidencia" style="max-width: 100%; max-height: 400px; object-fit: contain; border-radius: 8px;">
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- No Incident Found -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Incidencia no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La incidencia solicitada no existe o no tienes permisos para verla.</p>
        <button @click="goToHome" class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
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
</style>
