<script setup>
import { ref, computed, onMounted } from 'vue';
import { useReserveStore } from '@/services/store/reserve.store';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import cancelReserveModal from '@/components/reserves/cancelReserveModal.vue';

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
const filterModal = ref(false)

const userFilters = ref({
  hideCanceled: true,
  hidePast: true,
  status: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: '',
})

const activeFilterCount = computed(() => {
  let count = 0
  if (!userFilters.value.hideCanceled) count++
  if (!userFilters.value.hidePast) count++
  if (userFilters.value.status && userFilters.value.status !== '') count++
  if (userFilters.value.date_to) count++
  return count
})

const filteredReserves = computed(() => {
  let list = reserves.value
  if (userFilters.value.hideCanceled) {
    list = list.filter(r => r.status != 0)
  }
  if (userFilters.value.hidePast) {
    list = list.filter(r => r.date >= moment().format('YYYY-MM-DD'))
  }
  if (userFilters.value.status && userFilters.value.status !== '') {
    list = list.filter(r => r.status == userFilters.value.status)
  }
  if (userFilters.value.date_to) {
    list = list.filter(r => r.date <= userFilters.value.date_to)
  }
  return list
})

const getReserves = () => {
  loading.value = true;
  reserveStore.getReservesByUser()
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

const resetFilters = () => {
  userFilters.value = {
    hideCanceled: true,
    hidePast: true,
    status: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: '',
  }
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
const getPaymentStatus = (booking) => {
  if (booking.amount > 0) {
    return !booking.pay
      ? 'No pagada'
      : booking.pay.status == 1
        ? 'Pendiente de aprob.'
        : 'Pagado';
  }
  return booking.status == 3 ? 'Confirmado' : 'Cancelado';
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`;
  }
  return 'Gratis';
}
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL
onMounted(() => {
  getReserves();
});

const statusOptions = [
  { label: 'Todos', value: '' },
  { label: 'Cancelada', value: 0 },
  { label: 'Pago pendiente', value: 1 },
  { label: 'Pendiente de aprob.', value: 2 },
  { label: 'Exitoso', value: 3 },
]
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
     <div class="reserve-list-footer px-4 flex md:justify-center items-center md:w-full md:px-12"
      style="height: 12%;">
      <div class="flex items-center w-full gap-2 md:mx-24">
        <q-btn color="primary" unelevated class="flex-1 createBookingButton"
          style="border-radius: 0.5rem;" @click="goTo('/client/reserves/form/add')">
          <div class="flex items-center py-1">
            <q-icon name="eva-plus-outline" />
            <div class="q-pt-none text-bold pl-1">
              Agregar reserva
            </div>
          </div>
        </q-btn>
        <q-btn flat round color="grey-8" size="md" @click="filterModal = true" class="filter-btn">
          <q-icon name="eva-funnel-outline" size="1.4rem" />
          <q-badge v-if="activeFilterCount > 0" color="primary" floating>{{ activeFilterCount }}</q-badge>
        </q-btn>
      </div>
    </div>
    <div class="" style="height: 88%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />

      </div>

      <!-- Content -->
      <div v-else class="px-4 pb-6 md:px-28">
        <!-- Lista de reservas -->
        <div v-if="filteredReserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in filteredReserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;">

            <!-- Sección superior - Detalles de la reserva -->
            <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
              <!-- Header con nombre y estado -->
              <div class="flex justify-between items-start mb-2">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-1">
                    {{ reserve.comun_area?.name || 'Área Común' }}
                  </h3>
                  <div v-if="reserve.departament" class="text-sm text-gray-500 mb-2">
                    Unidad #{{ reserve.departament.number }}
                  </div>
                </div>
                <!-- Estado badge -->
                <span :class="'bg-' + reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <!-- Contenido principal con imagen y detalles -->
              <div class="flex items-center space-x-4">
                <!-- Imagen del área -->
                <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0">
                  <div class="boxItem_list_v2">
                    <div class="flex justify-center items-center h-full w-full ">
                      <img :src="urlMedia + '/images/icons/' + (reserve.comun_area?.icon || 'default') + '.svg'" alt=""
                        style="height:100%">
                    </div>
                  </div>
                </div>

                <!-- Detalles de la reserva -->
                <div class="flex-1 space-y-2">
                  <!-- Fechas -->
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>

                  <!-- Horario -->
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ reserve.time_from }} - {{ reserve.time_to }}
                    </span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <div v-html="iconsApp.moneyIcon" />
                    <span class="font-medium">
                      {{ getPaymentAmount(reserve) }} 
                      <q-chip color="primary"  v-if="reserve.type == 2">
                        <div class="text-white" style="font-weight:600; font-size:0.8rem"> 
                          {{ reserve.type_label }}
                        </div>
                      </q-chip>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sección inferior - Estado de pago -->
            <div class="p-4 bg-gray-50">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <svg v-if="reserve.status == 3" class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                  </svg>
                  <svg v-else class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                  </svg>
                  <span class="text-sm font-medium text-gray-700">{{ getPaymentStatus(reserve) }}</span>
                </div>
                <div class="flex items-center">
                  <div v-if="reserve.type == 4" class="mr-3">
                    <q-chip color="primary" >
                      <div class="text-white" style="font-weight:600; font-size:0.8rem"> 
                        {{ reserve.type_label }}
                      </div>
                    </q-chip>
                  </div>
                  <q-btn unelevated rounded color="warning" size="sm" class="ml-3" v-if="reserve.status == 1"
                    @click="goTo('/client/reserves/pay-reserve/' + reserve.id)">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Proceder con el pago
                    </q-tooltip>
                    <div v-html="iconsApp.procedToPay"></div>
                  </q-btn>
                  <q-btn unelevated rounded color="teal-7" size="sm" class="ml-3"
                    v-if="reserve.status == 3 && reserve.comun_area?.has_extension && !reserve.has_extension"
                    @click="goTo('/client/reserves/extend/' + reserve.id)">
                    <q-tooltip class="bg-primary text-white text-body2" :offset="[10, 10]">
                      Extender tiempo
                    </q-tooltip>
                    <q-icon name="eva-clock-outline" size="1.1rem" />
                  </q-btn>
                  <q-btn unelevated rounded color="indigo-7" size="sm" class="ml-3"
                    v-if="reserve.status == 3 && reserve.comun_area?.type == 4"
                    @click="goTo('/client/reserves/guests/' + reserve.id)">
                    <q-tooltip class="bg-primary text-white text-body2" :offset="[10, 10]">
                      Lista de invitados
                    </q-tooltip>
                    <q-icon name="eva-list-outline" size="1.1rem" />
                  </q-btn>
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                    <div v-html="iconsApp.optionsBook" />
                    <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="goTo('/client/reserves/view/' + reserve.id)">
                          <q-item-section>Ver detalles</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="showDialog($event)" data-dialog="cancel"
                          :data-reserve="reserve.id" v-if="reserve.status != 0">
                          <q-item-section>Cancelar reserva</q-item-section>
                        </q-item>
                        <q-separator />
                        <q-item clickable v-close-popup v-if="reserve.status == 1"
                          @click="goTo('/client/reserves/pay-reserve/' + reserve.id)">
                          <q-item-section>Pagar</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup
                          v-if="reserve.status == 3 && reserve.comun_area?.has_extension && !reserve.has_extension"
                          @click="goTo('/client/reserves/extend/' + reserve.id)">
                          <q-item-section>Extender tiempo</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup v-if="reserve.status == 3">
                          <q-item-section>Descarga pase</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup v-if="reserve.status == 3 && reserve.comun_area?.type == 4"
                          @click="goTo('/client/reserves/guests/' + reserve.id)">
                          <q-item-section>Lista de invitados</q-item-section>
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
          <h3 class="text-lg font-semibold text-gray-900 mb-2" v-if="activeFilterCount > 0">Sin resultados</h3>
          <h3 class="text-lg font-semibold text-gray-900 mb-2" v-else>No tienes reservas</h3>
          <p class="text-gray-600 text-center mb-6" v-if="activeFilterCount > 0">Ninguna reserva coincide con los filtros actuales. Intenta ajustarlos.</p>
          <p class="text-gray-600 text-center mb-6" v-else>Aún no has realizado ninguna reserva de áreas comunes.</p>
        </div>
      </div>
    </div>
    <!-- Filtro modal -->
    <q-dialog v-model="filterModal" persistent>
      <q-card style="min-width: 320px; border-radius: 1rem;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6 text-bold">Filtros</div>
          <q-space />
          <q-btn icon="close" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section class="q-pt-md">
          <q-list>
            <q-item tag="label" v-ripple>
              <q-item-section avatar>
                <q-checkbox v-model="userFilters.hideCanceled" checked-icon="check" color="primary" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Ocultar canceladas</q-item-label>
              </q-item-section>
            </q-item>
            <q-item tag="label" v-ripple>
              <q-item-section avatar>
                <q-checkbox v-model="userFilters.hidePast" checked-icon="check" color="primary" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Solo próximas (>= hoy)</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
          <q-select v-model="userFilters.status" :options="statusOptions" label="Estado" emit-value map-options
            class="q-mt-md" dense outlined />
          <q-input v-model="userFilters.date_to" label="Hasta fecha" type="date" dense outlined class="q-mt-md" />
        </q-card-section>
        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat label="Limpiar" color="grey-7" @click="resetFilters" />
          <q-btn unelevated label="Aplicar" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
   
    <template v-if="Object.values(selectedReserve).length > 0">
      <cancelReserveModal :dialog="(dialog == 'cancel')" :reserve="selectedReserve" @closeModal="dialog = ''"
        @updateList="getReserves()" />
    </template>
  </div>
</template>

<style scoped lang="scss">
/* Estilos adicionales si es necesario */
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

.filter-btn {
  position: relative;
}

.boxItem_list_v2 {
  border-radius: 0.8rem;
  overflow: visible;
  position: relative;
  // border: 2px solid rgb(3, 156, 195) ;
  width: 100%;
  //box-shadow: 0px 0.1rem 1rem 0px rgba(0, 0, 0, 0.205);
  background-repeat: no-repeat;
  background-size: cover;

  transition: all 0.7s ease-in-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.03);
  }
}
</style>