<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useVisitStore } from '@/services/store/visits.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const visitStore = useVisitStore()

const visit = ref(null)
const loading = ref(false)
const error = ref(null)

const getVisitById = async (id) => {
  try {
    loading.value = true
    error.value = null
    const response = await visitStore.getVisitById(id)
    visit.value = response.data
  } catch (err) {
    console.error('Error al obtener la visita:', err)
    error.value = err || 'Error al cargar la visita'
  } finally {
    loading.value = false
  }
}

const goToHome = () => {
  router.go(-1)
}

const visitId = route.params.id || route.query.id

onMounted(() => {
  if (visitId) {
    getVisitById(visitId)
  } else {
    error.value = 'ID de visita no proporcionado'
  }
})

const reloadVisit = () => {
  if (visitId) {
    getVisitById(visitId)
  }
}
</script>

<template>
  <div class="h-full relative" style="overflow: auto;">
    <div class="relative pt-8 pb-8 md:px-6 px-3">
      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-4">Cargando visita...</p>
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
        <button @click="reloadVisit"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="visit" class="flex flex-col items-center md:px-28 md:mx-28 pb-8">
        <div class="bg-white rounded-xl  border border-gray-300 flex flex-col items-center w-full">
          <div class="row w-full mb-3 items-end">
            <div class="flex flex-col items-start col-md-6 col-6 md:pl-5 pl-3">
              <div class="mb-4 pt-5">
                <q-icon name="eva-person-outline" size="4rem" color="primary" />
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">
                {{ visit.fullname }}
              </h1>
            </div>
            <div class="col-md-6 col-6 text-right">
              <div class="flex justify-end">
                <div class="p-4 dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creada el:</span> {{
                    moment(visit.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
              <div class="mt-4 md:pr-5 pr-3">
                <div class="text-grey-7 font-medium text-md">Departamento:</div>
                <div class="text-primary text-md font-bold">{{ visit.departament?.number }}</div>
              </div>
            </div>
          </div>
          
          <!-- Tarjeta de detalles -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              <!-- Estado -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado</span>
                <span class="font-semibold" :class="'text-' + visit.status_color">{{ visit.status_label }}</span>
              </div>

              <!-- DNI -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Documento de identidad</span>
                <span class="text-gray-900 font-semibold">{{ visit.dni }}</span>
              </div>

              <!-- Fecha de visita -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha programada</span>
                <span class="text-gray-900 font-semibold">{{ new Date(visit.date).toLocaleDateString('es-ES') }}</span>
              </div>

              <!-- Hora -->
              <div class="flex justify-between items-center pb-2" v-if="visit.hour" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Hora de llegada</span>
                <span class="text-gray-900 font-semibold">{{ visit.hour }}</span>
              </div>

              <!-- Tipo de visita -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Tipo de visita</span>
                <span class="text-gray-900 font-semibold">
                  <q-chip color="primary" size="0.8rem">
                    <div class="text-white" style="font-weight:600; font-size:0.8rem">
                      {{ visit.type_label }}
                    </div>
                  </q-chip>
                </span>
              </div>

              <!-- Descripción -->
              <div class="flex justify-between items-center pb-2" v-if="visit.description" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Descripción</span>
                <span class="text-gray-900 font-semibold">{{ visit.description }}</span>
              </div>
              
              <!-- Fecha de llegada confirmada -->
              <div class="flex justify-between items-center pb-2" v-if="visit.arrived_date" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Llegada confirmada</span>
                <span class="text-gray-900 font-semibold">{{ moment(visit.arrived_date).format('DD/MM/YYYY HH:mm') }}</span>
              </div>

            </div>
          </div>
          
          <div class="w-full pb-4 px-4">
             <button @click="goToHome" class="w-full py-4 border border-gray-300 rounded-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
              Volver a la lista
             </button>
          </div>
        </div>
      </div>

      <!-- No Visit Found -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Visita no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La visita solicitada no existe o no tienes permisos para verla.</p>
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
