<script setup>
import { ref, onMounted } from 'vue';
import { useReserveStore } from '@/services/store/reserve.store';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import cancelReserveModal from '@/components/reserves/cancelReserveModal.vue';
import filterModal from '@/components/reserves/filterModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
      '_'
  ),
})

const reserves = ref([]);
const loading = ref(false);
const reserveStore = useReserveStore();
const router = useRouter();
const dialog = ref('');
const selectedReserve = ref({})
const filter = ref({
  status: 4,
  area_id: '',
  date_from: '',
  date_to: '',
  amount_type: '',
  sort_by: 'created_at',
  sort_dir: 'desc'
})
const getReserves = () =>{
  loading.value = true;
  reserveStore.getReservesByUser(filter.value)
    .then((response) => {
      if (response.code !== 200) throw response;
      reserves.value = response.data;
    })
    .catch((response) => {
      console.log(response);
    })
    .finally(() => {
      loading.value = false;
    });
}
const getReserveWithFilter = (newFilter) =>{
  filter.value = { ...filter.value, ...newFilter };
  getReserves();
}

const goTo = (url) => {
  router.push(url);
}

const showDialog = (e) => {
  const dialogData = getDialogData(e)
  selectReserve(dialogData.reserve)
  setTimeout(() => {
    dialog.value = dialogData.dialog;
  }, 500);
}

const selectReserve = (id) => {
  selectedReserve.value = reserves.value.find(reserve => reserve.id == id)
}
const getDialogData = (e) => {
  return e.target.closest('.q-item').dataset
}


const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`;
  }
  return 'Gratis';
}

const formatTimeNoSeconds = (t) => {
  if (t == null || t === '') return '';
  const m = moment(String(t).trim(), ['HH:mm:ss', 'H:mm:ss', 'HH:mm', 'H:mm'], true);
  return m.isValid() ? m.format('HH:mm') : String(t).trim();
};

onMounted(() => {
  getReserves();
});
</script>
<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 pb-6 pt-3 md:px-28">
        <div class="flex justify-end md:pr-5 pr-1">
          <q-btn
            outline
            color="primary"
            icon="eva-funnel-outline"
            @click="dialog = 'filter'"
          />
        </div>
        <!-- Lista de reservas -->
        <div v-if="reserves.length > 0" class="space-y-3 pt-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5" style="position: relative;">

            <!-- Sección superior - Detalles de la reserva -->
            <div class="px-0 pb-4 pt-2 border-b border-dashed border-gray-300">
              <!-- Header con nombre y estado -->
              <div class="flex justify-between items-start mb-2 px-4">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-0">
                    #{{ reserve.booking_number }}
                  </h3>
                </div>
                <!-- Estado badge -->
                <span :class="'bg-'+reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <!-- Contenido principal con imagen y detalles -->
              <div class="flex row items-end ">
                <!-- Imagen del área -->

                <!-- Detalles de la reserva -->
                <div class="flex-1  col-12 pl-4">
                  <!-- Area -->
                  <div class="flex items-center text-sm text-gray-700" style="margin-top: 3px;">
                    <svg style="transform: translateX(-3px);" width="24px" height="24px" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" stroke-width="2" stroke="#374151" fill="none"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z" stroke-linecap="round"></path><path d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z" stroke-linecap="round"></path><line x1="28.1" y1="24.86" x2="21.06" y2="24.86" stroke-linecap="round"></line><line x1="43.66" y1="32.41" x2="40.14" y2="32.41" stroke-linecap="round"></line><line x1="43.66" y1="36.9" x2="40.14" y2="36.9" stroke-linecap="round"></line><line x1="43.66" y1="41.71" x2="40.14" y2="41.71" stroke-linecap="round"></line><line x1="43.66" y1="46.19" x2="40.14" y2="46.19" stroke-linecap="round"></line><line x1="28.1" y1="30.44" x2="21.06" y2="30.44" stroke-linecap="round"></line><line x1="28.1" y1="35.94" x2="21.06" y2="35.94" stroke-linecap="round"></line><line x1="28.1" y1="41.44" x2="21.06" y2="41.44" stroke-linecap="round"></line><line x1="28.1" y1="46.94" x2="21.06" y2="46.94" stroke-linecap="round"></line><line x1="9.46" y1="52.73" x2="54.54" y2="52.73" stroke-linecap="round"></line></g></svg>
                    <span class="font-medium">{{ reserve.comun_area.name }}</span>
                  </div>
                  <!-- Fechas -->
                  <div class="flex items-center text-sm text-gray-700" style="margin-top: 3px;">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>

                  <!-- Horario -->
                  <div class="flex items-center text-sm text-gray-700" style="margin-top: 3px;">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTimeNoSeconds(reserve.time_from) }} - {{ formatTimeNoSeconds(reserve.time_to) }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700" style="margin-top: 3px;">
                    <div v-html="iconsApp.moneyIcon" />
                    <span class="font-medium">
                      {{ getPaymentAmount(reserve) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sección inferior - Estado de pago -->
            <div class="p-4 bg-gray-50">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-sm font-medium text-gray-700">{{ reserve.user.name }} </span>
                </div>
                <div class="flex items-center">
                  <q-btn unelevated rounded color="warning" size="sm" class="ml-3" v-if="reserve.status == 2"  
                    @click="goTo('/admin/pay/validate/' + reserve.pay.id)">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Validar pago
                    </q-tooltip>
                    <div v-html="iconsApp.processPay"></div>
                  </q-btn>
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer" >
                    <div v-html="iconsApp.optionsBook"></div>
                    <q-menu>
                    <q-list style="min-width: 150px">
                      <q-item clickable v-close-popup @click="goTo('/client/reserves/view/'+reserve.id)">
                        <q-item-section>Ver detalles</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup @click="showDialog($event)" data-dialog="cancel" :data-reserve="reserve.id" v-if="reserve.status != 0">
                        <q-item-section>Cancelar reserva</q-item-section>
                      </q-item>
                      <q-separator />
                    </q-list>
                  </q-menu>
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
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay reservas🥺</h3>
          <p class="text-gray-600 text-center mb-6">Aún han realizado ninguna reserva de áreas comunes.</p>
        </div>
      </div>
    </div>
    <!-- Botón flotante para crear reserva -->
    <filterModal 
        :dialog="(dialog == 'filter')" 
        @closeModal="dialog = ''"
        @updateList="getReserveWithFilter"
      />
    <template v-if="Object.values(selectedReserve).length > 0">
      <cancelReserveModal 
        :dialog="(dialog == 'cancel')" 
        :reserve="selectedReserve" 
        @closeModal="dialog = ''"
        @updateList="getReserves()"
      />
    </template>
  </div>
</template> 

<style scoped>
/* Estilos adicionales si es necesario */
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>