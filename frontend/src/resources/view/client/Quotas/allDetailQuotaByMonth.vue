<script setup>
import { ref, onMounted, computed } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useRoute, useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';
// import quotaFilterModal from '@/components/quotas/quotaFilterModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const quotas = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();

const route = useRoute()


const isAdminRoute = computed(() => {
  return route.name === 'quotasDetailByMonthAdmin'
});
const getQuotas = () => {
  loading.value = true;
  quotaStore.getQuotaByMonth(route.params.month, route.query.year)
    .then((response) => {
      if (response.code !== 200) throw response;

      quotas.value = response.data;
    })
    .catch((response) => {
      console.log(response);
    })
    .finally(() => {
      loading.value = false;
    });
}
const getTitleQuota = (quota) => {
  let typeOperation = quota.type !== 3
    ? 'Mensualidad: ' + quota.month_label
    : 'Cuota especial'
  return typeOperation
}


const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
}


onMounted(() => {
  getQuotas();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">

    <!-- Lista de pagos -->
    <div class="h-full" style="overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de pagos -->
        <div v-if="quotas.length > 0" class="space-y-5 md:px-5">
          <div v-for="quota in quotas" :key="quota.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative; border: 1px solid lightgrey">

            <!-- Sección superior - Detalles del pago -->
            <div class="px-4 pb-2 pt-2 md:pt-4">
              <!-- Header con título y badge -->
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <div class="flex-1">
                  <h3 class=" mb-1">
                    <div class="text-lg font-bold text-gray-900">
                      {{ getTitleQuota(quota) }}
                    </div>
                    <div v-if="isAdminRoute" class="pt-1 text-xs font-medium text-gray-500">
                      {{ quota.departament.owner.name }}
                    </div>
                    <!-- Badge "New" opcional -->
                    <span class="absolute top-2 right-2 text-white text-xs font-bold px-2 py-1 rounded-md"
                      :class="`bg-${quota.status_color}`">
                      {{ quota.status_label }}
                    </span>
                  </h3>
                </div>
                <!-- Estado badge -->

              </div>

              <!-- Contenido principal con detalle -->
              <div class="space-y-2 pt-3 pb-2">
                <div class="row items-center ">
                  <!-- Apartamento -->
                  <div class="flex items-center text-sm text-gray-700 col-3 col-md-2 ">
                    <svg style="transform: translateX(-3px);" width="23px" height="23px" viewBox="0 0 64 64"
                      xmlns="http://www.w3.org/2000/svg" stroke-width="2" stroke="#374151" fill="none">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier">
                        <path d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z"
                          stroke-linecap="round"></path>
                        <path d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z" stroke-linecap="round"></path>
                        <line x1="28.1" y1="24.86" x2="21.06" y2="24.86" stroke-linecap="round"></line>
                        <line x1="43.66" y1="32.41" x2="40.14" y2="32.41" stroke-linecap="round"></line>
                        <line x1="43.66" y1="36.9" x2="40.14" y2="36.9" stroke-linecap="round"></line>
                        <line x1="43.66" y1="41.71" x2="40.14" y2="41.71" stroke-linecap="round"></line>
                        <line x1="43.66" y1="46.19" x2="40.14" y2="46.19" stroke-linecap="round"></line>
                        <line x1="28.1" y1="30.44" x2="21.06" y2="30.44" stroke-linecap="round"></line>
                        <line x1="28.1" y1="35.94" x2="21.06" y2="35.94" stroke-linecap="round"></line>
                        <line x1="28.1" y1="41.44" x2="21.06" y2="41.44" stroke-linecap="round"></line>
                        <line x1="28.1" y1="46.94" x2="21.06" y2="46.94" stroke-linecap="round"></line>
                        <line x1="9.46" y1="52.73" x2="54.54" y2="52.73" stroke-linecap="round"></line>
                      </g>
                    </svg>
                    <span class="font-medium">{{ quota.departament.number }}</span>
                  </div>
                  <!-- Monto -->
                  <div class="flex items-center text-sm text-gray-700 pl-2 md:pl-0 col-4 col-md-2 ">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                      </path>
                    </svg>
                    <span class="font-medium">S/. {{ quota.maintenance_amount }}</span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700 pl-2 md:pl-0 col-4 col-md-2 ">
                    <svg class="w-4 h-4 mr-1 text-gray-500" fill="#6a7282" version="1.1" id="Layer_1"
                      xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                      viewBox="0 0 511.999 511.999" xml:space="preserve" stroke="#6a7282">
                      <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                      <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                      <g id="SVGRepo_iconCarrier">
                        <g>
                          <g>
                            <path
                              d="M451.302,300.843V131.949h-92.379v15.872h76.508v153.022h-89.688V253.38h60.678v-15.872H216.415v15.872h113.456v47.463 H308.76v68.651h163.656v-68.651H451.302z M456.543,353.623H324.631v-36.907h131.912V353.623z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M337.807,147.821v-15.872h-92.344v-58.02h52.78V0H124.032v73.929h52.78v58.02H124.07v-26.39H55.456V81.828H39.584v221.673 h15.872V279.77h68.614v-26.39h71.233v-15.872H124.07v-89.688H337.807z M229.591,131.949h-0.001h-36.906v-58.02h36.907V131.949z M139.903,58.057V42.261h102.901V26.39H139.903V15.872H282.37v42.185H139.903z M108.198,263.898H55.456V121.431h52.742V263.898z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="71.253" y="139.884" width="15.872" height="63.335"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="71.253" y="219.052" width="15.872" height="21.112"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <polygon
                              points="290.306,163.616 290.306,179.488 403.763,179.488 403.763,203.221 419.635,203.221 419.635,163.616 ">
                            </polygon>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="242.807" y="163.617" width="26.39" height="15.872"></rect>
                          </g>
                        </g>
                        <g>
                          <g>
                            <path
                              d="M401.748,387.902l-5.883-6.504l-5.885,6.504c-6.11,6.753-59.243,66.982-43.742,101.913 c6.533,14.721,23.229,22.184,49.626,22.184c26.397,0,43.094-7.464,49.626-22.184C460.992,454.885,407.859,394.654,401.748,387.902 z M430.984,483.378c-4.677,10.538-21.679,12.75-35.118,12.75c-13.438,0-30.439-2.211-35.117-12.746 c-4.465-10.052,0.98-28.078,15.329-50.758c7.025-11.103,14.723-20.994,19.84-27.221 C415.524,428.955,438.75,465.88,430.984,483.378z">
                            </path>
                          </g>
                        </g>
                        <g>
                          <g>
                            <rect x="380.035" y="464.463" width="21.111" height="15.872"></rect>
                          </g>
                        </g>
                      </g>
                    </svg>
                    <span class="font-medium" v-if="quota.type == 1">S/. {{ quota.water_amount }}</span>
                    <span class="font-medium" v-else>---</span>

                  </div>
                  <!-- Fecha de pago -->
                  <div class="flex items-center text-sm text-gray-700 col-12 pt-2 md:pt-0 col-md-4 ">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Fecha limite: {{ formatDate(quota.due_date) }}</span>
                  </div>
                </div>

              </div>
            </div>

            <!-- Sección inferior - Acciones -->
            <!-- <div class="px-4 py-2 md:py-3 bg-gray-50 border-t cursor-pointer bg-primary"  @click="goTo(quota)">
              <div class="flex justify-center items-center">
                <div class="flex items-center">
                  <q-icon 
                    name="eva-eye-outline" 
                    color="white"
                    size="1.5rem"
                  />
                  <span class="ml-1 text-sm font-medium text-white">Ver detalles</span>
                </div>
              </div>
            </div> -->
          </div>
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <div v-html="appIcons.mensuality" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No tienes cuotas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no se ha emitido tu primera cuota.</p>
        </div>
      </div>
    </div>

    <!-- Modal de filtros -->

  </div>
</template>

<style scoped>
/* Estilos adicionales si es necesario */
</style>
