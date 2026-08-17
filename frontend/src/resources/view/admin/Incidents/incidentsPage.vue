<script setup>
import { ref, onMounted } from 'vue'
import { useIncidentStore } from '@/services/store/incident.store'
import { useRouter } from 'vue-router'
import moment from 'moment'

const incidents = ref([])
const loading = ref(true)
const incidentStore = useIncidentStore()
const router = useRouter()

const getIncidents = () => {
  loading.value = true
  incidentStore
    .getIncidents()
    .then((response) => {
      incidents.value = response.data.data || response
    })
    .catch((error) => {
      console.error(error)
    })
    .finally(() => {
      loading.value = false
    })
}

const goToStatus = (incidentId) => {
  router.push(`/admin/incidents/${incidentId}/status`)
}

const getStatusColor = (status) => {
  if (status === 1) return 'warning'
  if (status === 4) return 'positive'
  return 'info'
}

onMounted(() => {
  getIncidents()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de incidencias -->
        <div v-if="incidents.length > 0" class="space-y-3 md:px-5">
          <div
            v-for="incident in incidents"
            :key="incident.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;"
          >
            <!-- Sección superior - Detalles de la incidencia -->
            <div class="px-4 pb-4 pt-2">
              <!-- Header con nombre y estado -->
              <div class="mb-2">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-2 pr-24">
                    {{ incident.title }}
                  </h3>
                </div>
                <div class="flex items-center text-sm text-gray-700">
                  <div class="font-medium ellipsis" style="width: 90%;">
                    {{ incidentStore.typeLabels[incident.type] }}
                  </div>
                </div>
                <!-- Estado badge -->
                <span
                  :class="'bg-' + getStatusColor(incident.status)"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve"
                >
                  {{ incidentStore.statusLabels[incident.status] }}
                </span>
              </div>

              <!-- Contenido principal con icono y detalles -->
              <div class="flex items-center space-x-4">
                <!-- Icono de la incidencia -->
                <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0">
                  <div class="boxItem_list_v2">
                    <div
                      class="flex justify-center items-center h-full w-full bg-blue-50 text-blue-500"
                      style="border-radius: 0.8rem;"
                    >
                      <q-icon name="eva-alert-circle-outline" size="2rem" />
                    </div>
                  </div>
                </div>

                <!-- Detalles de la incidencia -->
                <div class="flex-1 space-y-2">
                  <div v-if="incident.description" class="text-sm text-gray-600 line-clamp-2">
                    {{ incident.description }}
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <svg
                      class="w-4 h-4 mr-2 text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                      ></path>
                    </svg>
                    <span class="font-medium">
                      {{ incident.user?.name || 'Usuario desconocido' }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <svg
                      class="w-4 h-4 mr-2 text-gray-500"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                      ></path>
                    </svg>
                    <span class="font-medium">
                      Reportado: {{ moment(incident.created_at).format('DD MMM YYYY') }} · Ocurrió:
                      {{ moment(incident.date).format('DD MMM YYYY') }} · {{ incident.hour }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Acciones -->
              <div class="flex justify-end mt-3">
                <q-btn
                  color="primary"
                  outline
                  no-caps
                  unelevated
                  style="border-radius: 0.5rem;"
                  @click="goToStatus(incident.id)"
                >
                  <q-icon name="eva-edit-outline" class="mr-1" size="1.1rem" />
                  Cambiar estado
                </q-btn>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-alert-circle-outline" color="blue-500" size="2.5rem" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay incidencias</h3>
          <p class="text-gray-600 text-center mb-6">
            Los residentes aún no han reportado incidencias.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}

.boxItem_list_v2 {
  border-radius: 0.8rem;
  overflow: visible;
  position: relative;
  width: 100%;
  height: 100%;
  background-repeat: no-repeat;
  background-size: cover;

  transition: all 0.7s ease-in-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.03);
  }
}
</style>