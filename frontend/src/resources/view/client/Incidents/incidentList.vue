<script setup>
import { ref, onMounted } from 'vue';
import { useIncidentStore } from '@/services/store/incident.store';
import { useRouter } from 'vue-router';
import moment from 'moment';

const incidents = ref([]);
const loading = ref(true);
const incidentStore = useIncidentStore();
const router = useRouter();

const getIncidents = () => {
  loading.value = true;
  incidentStore.getIncidents()
    .then((response) => {
      // Laravel paginate returns items in response.data or response
      incidents.value = response.data.data || response; 
    })
    .catch((error) => {
      console.error(error);
    })
    .finally(() => {
      loading.value = false;
    });
}

const goToCreate = () => {
  router.push('/client/incidents/create');
}

onMounted(() => {
  getIncidents();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="reserve-list-footer px-4 flex justify-center items-center md:w-full md:px-12"
      style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem; width: 100%;" @click="goToCreate()">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Reportar incidencia
          </div>
        </div>
      </q-btn>
    </div>
    <div class="" style="height: 90%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de incidencias -->
        <div v-if="incidents.length > 0" class="space-y-3 md:px-5">
          <div v-for="incident in incidents" :key="incident.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 cursor-pointer"
            style="position: relative;" @click="router.push(`/client/incidents/view/${incident.id}`)">

            <!-- Sección superior - Detalles de la incidencia -->
            <div class="px-4  pb-4 pt-2 ">
              <!-- Header con nombre y estado -->
              <div class="mb-2">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-2">
                    {{ incident.title }} 
                  </h3>
                </div>
                <div class="flex items-center text-sm text-gray-700 ">
                  <!-- <q-icon name="eva-bookmark-outline" class="w-4 h-4 mr-2 text-gray-500" size="1.2rem"/> -->
                  <div class="font-medium ellipsis " style="width: 90%;">
                    {{ incidentStore.typeLabels[incident.type] }}
                  </div>
                </div>
                <!-- Estado badge -->
                <span :class="'bg-' + (incident.status === 1 ? 'warning' : (incident.status === 4 ? 'positive' : 'info'))"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ incidentStore.statusLabels[incident.status] }}
                </span>
              </div>

              <!-- Contenido principal con icono y detalles -->
              <div class="flex items-center space-x-4">
                <!-- Icono de la incidencia -->
                <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0">
                  <div class="boxItem_list_v2">
                    <div class="flex justify-center items-center h-full w-full bg-blue-50 text-blue-500" style="border-radius: 0.8rem;">
                      <q-icon name="eva-alert-circle-outline" size="2rem" />
                    </div>
                  </div>
                </div>

                <!-- Detalles de la incidencia -->
                <div class="flex-1 space-y-2">
                  <!-- Fechas -->
                   <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Reportado: {{ moment(incident.created_at).format('DD MMM YYYY') }}</span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Ocurrio: {{ moment(incident.date).format('DD MMM YYYY') }}</span>
                  </div>
                  

                  <!-- Horario -->
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ incident.hour }}
                    </span>
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
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No tienes incidencias</h3>
          <p class="text-gray-600 text-center mb-6">Aún no has reportado ninguna incidencia.</p>
        </div>
      </div>
    </div>
    
    <!-- Botón flotante para reportar incidencia -->
    
  </div>
</template>

<style scoped lang="scss">
@media (max-width: 780px) {
  .reserve-list-footer {
    // padding-bottom: max(var(--safe-area-inset-bottom, env(safe-area-inset-bottom, 0px)), 48px);
  }
}

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
