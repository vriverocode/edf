<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useIncidentStore } from '@/services/store/incident.store'
import { Notify } from 'quasar'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const incidentStore = useIncidentStore()

const incident = ref(null)
const loading = ref(false)
const error = ref(null)

// Status change
const submittingStatus = ref(false)
const selectedStatus = ref(null)
const statusOptions = [
  { value: 1, label: 'Pendiente', color: 'warning' },
  { value: 2, label: 'Atendido', color: 'info' },
  { value: 3, label: 'Pendiente de aprobación', color: 'blue-7' },
  { value: 4, label: 'Resuelto', color: 'positive' },
]

// Responses
const responseMessage = ref('')
const submittingResponse = ref(false)

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2500 })
}

const incidentId = route.params.id || route.query.id

const getIncidentById = async (id) => {
  try {
    loading.value = true
    error.value = null
    const response = await incidentStore.getIncidentById(id)
    incident.value = response.data || response
    selectedStatus.value = String(incident.value.status)
  } catch (err) {
    console.error('Error al obtener la incidencia:', err)
    error.value = err || 'Error al cargar la incidencia'
  } finally {
    loading.value = false
  }
}

const goBack = () => {
  router.go(-1)
}

const reloadIncident = () => {
  if (incidentId) {
    getIncidentById(incidentId)
  }
}

const submitStatus = async () => {
  if (selectedStatus.value === null) {
    showNotify('negative', 'Selecciona el nuevo estado')
    return
  }
  submittingStatus.value = true
  try {
    await incidentStore.updateIncident(incidentId, { status: Number(selectedStatus.value) })
    showNotify('positive', 'Estado actualizado con éxito')
    await getIncidentById(incidentId)
  } catch (err) {
    showNotify('negative', err || 'Error al cambiar el estado')
  } finally {
    submittingStatus.value = false
  }
}

const submitResponse = async () => {
  if (!responseMessage.value.trim()) {
    showNotify('negative', 'Escribe un mensaje de respuesta')
    return
  }
  submittingResponse.value = true
  try {
    await incidentStore.storeResponse(incidentId, { message: responseMessage.value })
    showNotify('positive', 'Respuesta agregada con éxito')
    responseMessage.value = ''
    await getIncidentById(incidentId)
  } catch (err) {
    showNotify('negative', err || 'Error al agregar respuesta')
  } finally {
    submittingResponse.value = false
  }
}

const getStatusColor = (status) => {
  const opt = statusOptions.find((o) => o.value === Number(status))
  return opt?.color || 'grey-7'
}

onMounted(() => {
  if (incidentId) {
    getIncidentById(incidentId)
  } else {
    error.value = 'ID de incidencia no proporcionado'
  }
})
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
              <div class="mb-4 md:pt-5 pt-0">
                <q-icon name="eva-alert-circle-outline" size="4rem" color="primary" />
              </div>
              <h1 class="text-2xl font-bold text-gray-900 md:mb-2">
                {{ incident.title }}
              </h1>
            </div>
            <div class="col-md-6 col-12 text-right order-first md:order-last">
              <div class="flex justify-end">
                <div class="p-3 md:p-4 dateFact text-primary text-md font-bold">
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
                <q-chip
                  :color="getStatusColor(incident.status)"
                  text-color="white"
                  class="text-bold px-3"
                  dense
                >
                  {{ incidentStore.statusLabels[incident.status] }}
                </q-chip>
              </div>

              <!-- Reportado por -->
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Reportado por</span>
                <span class="text-gray-900 font-semibold">{{ incident.user?.name || 'Usuario desconocido' }}</span>
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
                <span class="text-gray-900 font-semibold">{{ moment(incident.date).format('DD/MM/YYYY') }} · {{ incident.hour }}</span>
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

        <!-- Sección: Cambiar estado -->
        <div class="bg-white rounded-xl border border-gray-300 w-full mt-5 p-5">
          <div class="text-lg font-bold text-gray-900 mb-4">
            <q-icon name="eva-edit-outline" size="1.4rem" class="mr-2" />
            Cambiar estado
          </div>
          <div class="flex flex-wrap gap-2 mb-4">
            <q-radio
              v-for="opt in statusOptions"
              :key="opt.value"
              v-model="selectedStatus"
              :val="String(opt.value)"
              :label="opt.label"
              :color="opt.color"
              class="col-12"
            />
          </div>
          <div class="flex justify-end">
            <q-btn
              color="primary"
              no-caps
              unelevated
              style="border-radius: 0.5rem;"
              :loading="submittingStatus"
              @click="submitStatus"
            >
              <div class="px-6 py-1">Actualizar estado</div>
            </q-btn>
          </div>
        </div>

        <!-- Sección: Respuestas -->
        <div class="bg-white rounded-xl border border-gray-300 w-full mt-5 p-5">
          <div class="text-lg font-bold text-gray-900 mb-4">
            <q-icon name="eva-message-circle-outline" size="1.4rem" class="mr-2" />
            Respuestas
          </div>

          <!-- Lista de respuestas existentes -->
          <div v-if="incident.responses && incident.responses.length > 0" class="space-y-3 mb-5">
            <div
              v-for="resp in incident.responses"
              :key="resp.id"
              class="bg-gray-50 rounded-lg p-4 border border-gray-200"
            >
              <div class="flex justify-between items-start mb-2">
                <div class="flex items-center">
                  <q-icon name="eva-person-outline" size="1.2rem" class="mr-2 text-gray-500" />
                  <span class="font-semibold text-gray-800">{{ resp.user?.name || 'Personal' }}</span>
                  <q-badge v-if="resp.user?.rol_id === 5" color="secondary" text-color="white" class="ml-2" label="Trabajador" />
                </div>
                <span class="text-xs text-gray-500">{{ moment(resp.created_at).format('DD/MM/YYYY HH:mm') }}</span>
              </div>
              <p class="text-gray-700 ml-6">{{ resp.message }}</p>
            </div>
          </div>
          <div v-else class="text-center text-gray-400 py-6 mb-4">
            <q-icon name="eva-message-square-outline" size="2.5rem" class="mb-2" />
            <p>No hay respuestas aún</p>
          </div>

          <!-- Formulario para agregar respuesta -->
          <div style="border-top: 1px solid lightgray;" class="pt-4">
            <div class="text-subtitle2 text-black mb-2">Agregar respuesta</div>
            <q-input
              v-model="responseMessage"
              type="textarea"
              autogrow
              dense
              borderless
              class="form__inputsR"
              placeholder="Escribe tu respuesta aquí..."
            />
            <div class="flex justify-end mt-3">
              <q-btn
                color="primary"
                no-caps
                unelevated
                style="border-radius: 0.5rem;"
                :loading="submittingResponse"
                :disable="!responseMessage.trim()"
                @click="submitResponse"
              >
                <q-icon name="eva-paper-plane-outline" class="mr-2" size="1.1rem" />
                <div class="py-1">Enviar respuesta</div>
              </q-btn>
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
        <button @click="goBack" class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver
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

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
</style>

