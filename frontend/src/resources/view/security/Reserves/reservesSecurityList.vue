<script setup>
import { ref, onMounted } from 'vue';
import { useReserveStore } from '@/services/store/reserve.store';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index';
import moment from 'moment';
import cancelReserveModal from '@/components/reserves/cancelReserveModal.vue';
import filterModal from '@/components/reserves/filterModal.vue';
import { Notify, Dialog } from 'quasar';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const reserves = ref([]);
const loading = ref(false);
const reserveStore = useReserveStore();
const router = useRouter();
const dialog = ref('');
const selectedReserve = ref({});
const filter = ref({
  status: -1,
  area_id: '',
  date_from: '',
  date_to: '',
  amount_type: '',
  sort_by: 'date',
  sort_dir: 'desc'
});

const quickFilters = [
  { key: 'hoy', label: 'Hoy' },
  { key: 'manana', label: 'Mañana' },
  { key: 'semana', label: 'Esta semana' },
]
const activeQuickFilter = ref('')

const applyQuickFilter = (key) => {
  activeQuickFilter.value = key
  const today = moment().format('YYYY-MM-DD')
  const base = { ...filter.value }
  switch (key) {
    case 'hoy':
      filter.value = { ...base, status: -1, date_from: today, date_to: today }
      break
    case 'manana':
      filter.value = { ...base, status: -1, date_from: moment().add(1, 'day').format('YYYY-MM-DD'), date_to: moment().add(1, 'day').format('YYYY-MM-DD') }
      break
    case 'semana':
      filter.value = { ...base, status: -1, date_from: today, date_to: moment().endOf('week').format('YYYY-MM-DD') }
      break
  }
  getReserves();
}

const getReserves = () => {
  loading.value = true;
  reserveStore.getBookingsForSecurity(filter.value)
    .then((response) => {
      reserves.value = response.data;
    })
    .catch((error) => {
      console.error(error);
    })
    .finally(() => {
      loading.value = false;
    });
}

const getReserveWithFilter = (newFilter) => {
  filter.value = { ...filter.value, ...newFilter };
  activeQuickFilter.value = '';
  getReserves();
}

const goTo = (url) => {
  router.push(url);
}

const showDialog = (e) => {
  const dialogData = getDialogData(e);
  selectReserve(dialogData.reserve);
  setTimeout(() => {
    dialog.value = dialogData.dialog;
  }, 500);
}

const selectReserve = (id) => {
  selectedReserve.value = reserves.value.find(reserve => reserve.id == id);
}

const getDialogData = (e) => {
  return e.target.closest('.q-item').dataset;
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

const openCancelReserve = (reserveId) => {
  selectReserve(reserveId)
  setTimeout(() => {
    dialog.value = 'cancel'
  }, 500)
}

const completeReserve = (reserve) => {
  const warrantyPrice = Number(reserve.comun_area?.warranty_price ?? 0)
  const needsRefund = warrantyPrice > 0 && reserve.pay?.status === 2
  const message = needsRefund
    ? `La reserva #${reserve.booking_number} pasará al estado "Pendiente de devolución". La devolución de S/. ${warrantyPrice.toFixed(2)} será registrada por administración.`
    : `La reserva #${reserve.booking_number} pasará al estado "Completada".`
  Dialog.create({
    title: 'Completar reserva',
    message,
    persistent: true,
    ok: { label: 'Confirmar', color: 'primary', flat: true },
    cancel: { label: 'Cancelar', color: 'grey-7', flat: true },
  }).onOk(() => {
    markAsComplete(reserve.id)
  })
}

const markAsComplete = async (reserveId) => {
    try {
        loading.value = true;
        await reserveStore.completeBooking(reserveId);
        Notify.create({ color: 'positive', message: 'Reserva completada' });
        getReserves();
    } catch (e) {
        Notify.create({ color: 'negative', message: 'Error al completar la reserva' });
    } finally {
        loading.value = false;
    }
}
const getDepartmentNumber = (reserve) => {
  return reserve.departament?.number || reserve.apartment_number || reserve.user?.units?.[0]?.number || '—'
}
onMounted(() => {
  const today = moment().format('YYYY-MM-DD')
  filter.value = { ...filter.value, status: -1, date_from: today, date_to: today, sort_by: 'date', sort_dir: 'desc' }
  activeQuickFilter.value = 'hoy'
  getReserves();
});
</script>
<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 pb-6 pt-3 md:px-28">
        <div class="row">

          <div class="flex flex-wrap gap-2 pt-3 pb-1 md:px-5 col-12 col-md-10">
            <q-btn
              v-for="qf in quickFilters"
              :key="qf.key"
              dense
              no-caps
              outline
              class="px-2"
              :label="qf.label"
              :color="activeQuickFilter === qf.key ? 'primary' : 'grey-3'"
              :text-color="activeQuickFilter === qf.key ? 'blue-5' : 'grey-8'"
              @click="applyQuickFilter(qf.key)"
            />
          </div>
          <div class="flex justify-end md:pr-5 pr-1 col-12 col-md-2">
            <q-btn outline color="primary" icon="eva-funnel-outline" @click="dialog = 'filter'" />
          </div>
        </div>
        <div v-if="reserves.length > 0" class="space-y-3 pt-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 mb-4" style="position: relative;">
            <div class="px-0 pb-4 pt-2 border-b border-dashed border-gray-300" @click="goTo('/client/reserves/view/'+reserve.id)">
              <div class="flex justify-between items-start mb-2 px-4">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-0">
                    #{{ reserve.booking_number }}
                  </h3>
                </div>
                <span :class="'bg-'+reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>
              <div class="flex row items-end ">
                <div class="flex-1 col-12 pl-4 row">
                  <div class="flex items-center col-6 text-sm text-gray-700" style="margin-top: 3px;">
                    <svg class="text-gray-500" style="transform: translateX(-3px); margin-right:1px" width="23px" height="23px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M10.5 6H9.5C9.22386 6 9 6.22386 9 6.5V7.5C9 7.77614 9.22386 8 9.5 8H10.5C10.7761 8 11 7.77614 11 7.5V6.5C11 6.22386 10.7761 6 10.5 6Z" fill="#4f4f4f"></path> <path d="M14.5 6H13.5C13.2239 6 13 6.22386 13 6.5V7.5C13 7.77614 13.2239 8 13.5 8H14.5C14.7761 8 15 7.77614 15 7.5V6.5C15 6.22386 14.7761 6 14.5 6Z" fill="#4f4f4f"></path> <path d="M10.5 9.5H9.5C9.22386 9.5 9 9.72386 9 10V11C9 11.2761 9.22386 11.5 9.5 11.5H10.5C10.7761 11.5 11 11.2761 11 11V10C11 9.72386 10.7761 9.5 10.5 9.5Z" fill="#4f4f4f"></path> <path d="M14.5 9.5H13.5C13.2239 9.5 13 9.72386 13 10V11C13 11.2761 13.2239 11.5 13.5 11.5H14.5C14.7761 11.5 15 11.2761 15 11V10C15 9.72386 14.7761 9.5 14.5 9.5Z" fill="#4f4f4f"></path> <path d="M10.5 13H9.5C9.22386 13 9 13.2239 9 13.5V14.5C9 14.7761 9.22386 15 9.5 15H10.5C10.7761 15 11 14.7761 11 14.5V13.5C11 13.2239 10.7761 13 10.5 13Z" fill="#4f4f4f"></path> <path d="M14.5 13H13.5C13.2239 13 13 13.2239 13 13.5V14.5C13 14.7761 13.2239 15 13.5 15H14.5C14.7761 15 15 14.7761 15 14.5V13.5C15 13.2239 14.7761 13 14.5 13Z" fill="#4f4f4f"></path> <path d="M18.25 19.25H17.75V4C17.7474 3.80189 17.6676 3.61263 17.5275 3.47253C17.3874 3.33244 17.1981 3.25259 17 3.25H7C6.80189 3.25259 6.61263 3.33244 6.47253 3.47253C6.33244 3.61263 6.25259 3.80189 6.25 4V19.25H5.75C5.55109 19.25 5.36032 19.329 5.21967 19.4697C5.07902 19.6103 5 19.8011 5 20C5 20.1989 5.07902 20.3897 5.21967 20.5303C5.36032 20.671 5.55109 20.75 5.75 20.75H18.25C18.4489 20.75 18.6397 20.671 18.7803 20.5303C18.921 20.3897 19 20.1989 19 20C19 19.8011 18.921 19.6103 18.7803 19.4697C18.6397 19.329 18.4489 19.25 18.25 19.25ZM16.25 19.25H11V17C11 16.8674 10.9473 16.7402 10.8536 16.6464C10.7598 16.5527 10.6326 16.5 10.5 16.5H9.5C9.36739 16.5 9.24021 16.5527 9.14645 16.6464C9.05268 16.7402 9 16.8674 9 17V19.25H7.75V4.75H16.25V19.25Z" fill="#4f4f4f"></path> </g></svg>
                    <span class="font-medium">Dpto: {{ getDepartmentNumber(reserve) }}</span>
                  </div>
                  <div class="flex items-center col-6 text-sm text-gray-700" style="margin-top: 3px;">
                    <svg style="transform: translateX(-3px);" width="24px" height="24px" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" stroke-width="2" stroke="#374151" fill="none"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"><path d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z" stroke-linecap="round"></path><path d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z" stroke-linecap="round"></path><line x1="28.1" y1="24.86" x2="21.06" y2="24.86" stroke-linecap="round"></line><line x1="43.66" y1="32.41" x2="40.14" y2="32.41" stroke-linecap="round"></line><line x1="43.66" y1="36.9" x2="40.14" y2="36.9" stroke-linecap="round"></line><line x1="43.66" y1="41.71" x2="40.14" y2="41.71" stroke-linecap="round"></line><line x1="43.66" y1="46.19" x2="40.14" y2="46.19" stroke-linecap="round"></line><line x1="28.1" y1="30.44" x2="21.06" y2="30.44" stroke-linecap="round"></line><line x1="28.1" y1="35.94" x2="21.06" y2="35.94" stroke-linecap="round"></line><line x1="28.1" y1="41.44" x2="21.06" y2="41.44" stroke-linecap="round"></line><line x1="28.1" y1="46.94" x2="21.06" y2="46.94" stroke-linecap="round"></line><line x1="9.46" y1="52.73" x2="54.54" y2="52.73" stroke-linecap="round"></line></g></svg>
                    <span class="font-medium">{{ reserve.comun_area.name }}</span>
                  </div>
                  <div class="flex items-center col-6 text-sm text-gray-700" style="margin-top: 3px;">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>
                  <div class="flex items-center col-6 text-sm text-gray-700" style="margin-top: 3px;">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTimeNoSeconds(reserve.time_from) }} - {{ formatTimeNoSeconds(reserve.time_to) }}
                    </span>
                  </div>
                  <div class="flex items-center col-6 text-sm text-gray-700" style="margin-top: 3px;">
                    <div v-html="iconsApp.moneyIcon" />
                    <span class="font-medium">
                      {{ getPaymentAmount(reserve) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-4 bg-gray-50">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-sm font-medium text-gray-700">{{ reserve.user?.name }} {{ reserve.user?.lastname }}</span>
                </div>
                <div class="flex items-center">
                  <div class="flex items-center">
                    <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer" >
                      <div v-html="iconsApp.optionsBook"></div>
                      <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="goTo('/client/reserves/view/'+reserve.id)">
                          <q-item-section>Ver detalles</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="openCancelReserve(reserve.id)" v-if="[1,2,3].includes(reserve.status)">
                          <q-item-section>Cancelar reserva</q-item-section>
                        </q-item>
                        <q-separator />
                      </q-list>
                    </q-menu>
                    </div>
                  </div>
                </div>
                <q-btn
                    v-if="reserve.status === 3"
                    dense
                    no-caps
                    unelevated
                    color="primary"
                    class="w-full mt-3 py-1"
                    icon="eva-checkmark-circle-2-outline"
                    label="Completar reserva"
                    style="border-radius: 0.4rem;"
                    @click="completeReserve(reserve)"
                  />
              </div>
              
            </div>
          </div>
        </div>
        <div v-else class="flex flex-col items-center justify-center py-20">  
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
              </path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay reservas</h3>
          <p class="text-gray-600 text-center mb-6">No se encontraron reservas con los filtros aplicados.</p>
        </div>
      </div>
    </div>
    <filterModal 
        :dialog="(dialog == 'filter')" 
        @closeModal="dialog = ''"
        @updateList="getReserveWithFilter"
      />
    <cancelReserveModal
        :dialog="(dialog == 'cancel')"
        :reserve="selectedReserve"
        via-maintenance
        @closeModal="dialog = ''"
        @updateList="getReserves"
      />
  </div>
</template> 

<style scoped>
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>
