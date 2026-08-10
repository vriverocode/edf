<script setup>
import { ref, computed, onMounted } from 'vue';
import { useReserveStore } from '@/services/store/reserve.store';
import { useAuthStore } from '@/services/store/auth.services';
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

const reserveStore = useReserveStore();
const authStore = useAuthStore();
const router = useRouter();

const reserves = ref([]);
const loading = ref(false);
const dialog = ref('');
const selectedReserve = ref({})
const filterModal = ref(false)

const page = ref(1)
const lastPage = ref(1)
const perPage = ref(10)

const activeQuickFilter = ref('upcoming')

const userFilters = ref({
  hideCanceled: true,
  hidePast: true,
  status: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: '',
  only_residents: false,
})

const activeFilterCount = computed(() => {
  let count = 0
  if (!userFilters.value.hideCanceled) count++
  if (!userFilters.value.hidePast) count++
  if (userFilters.value.status && userFilters.value.status !== '') count++
  if (userFilters.value.date_to) count++
  if (userFilters.value.only_residents) count++
  return count
})

const getApiParams = () => {
  const params = {
    page: page.value,
    per_page: perPage.value,
  }

  if (userFilters.value.status !== '') {
    params.status = String(userFilters.value.status)
  } else if (!userFilters.value.hideCanceled) {
    params.status = '4'
  }

  if (userFilters.value.hidePast) {
    params.date_from = moment().format('YYYY-MM-DD')
  }

  if (userFilters.value.date_to) {
    params.date_to = userFilters.value.date_to
  }

  if (userFilters.value.only_residents) {
    params.only_residents = true
  }

  return params
}

const getReserves = () => {
  loading.value = true
  reserveStore.getReservesByUser(getApiParams())
    .then((response) => {
      if (response.code !== 200) throw response
      const payload = response.data
      if (Array.isArray(payload)) {
        reserves.value = payload
        lastPage.value = 1
      } else {
        reserves.value = payload.data || []
        lastPage.value = payload.last_page || 1
      }
    })
    .catch(() => {
      reserves.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

const setQuickFilter = (filter) => {
  activeQuickFilter.value = filter
  switch (filter) {
    case 'all':
      userFilters.value = {
        hideCanceled: false,
        hidePast: false,
        status: '4',
        date_from: '',
        date_to: '',
        only_residents: false,
      }
      break
    case 'upcoming':
      userFilters.value = {
        hideCanceled: true,
        hidePast: true,
        status: '',
        date_from: moment().format('YYYY-MM-DD'),
        date_to: '',
        only_residents: false,
      }
      break
    case 'pending':
      userFilters.value = {
        hideCanceled: true,
        hidePast: false,
        status: '1,2',
        date_from: '',
        date_to: '',
        only_residents: false,
      }
      break
    case 'success':
      userFilters.value = {
        hideCanceled: true,
        hidePast: false,
        status: '3',
        date_from: '',
        date_to: '',
        only_residents: false,
      }
      break
    case 'cancelled':
      userFilters.value = {
        hideCanceled: false,
        hidePast: false,
        status: '0',
        date_from: '',
        date_to: '',
        only_residents: false,
      }
      break
  }
  page.value = 1
  getReserves()
}

const resetFilters = () => {
  userFilters.value = {
    hideCanceled: true,
    hidePast: true,
    status: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: '',
    only_residents: false,
  }
  activeQuickFilter.value = 'upcoming'
  page.value = 1
  getReserves()
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
    if (!booking.pay) return 'No pagada'
    const statuses = {
      0: 'Cancelado',
      1: 'Pendiente de aprob.',
      2: 'Exitoso',
      3: 'Rechazado',
      4: 'Reemb. parcial',
      5: 'Reembolsado',
      6: 'Pend. devolución',
    }
    return statuses[booking.pay.status] ?? 'Pagado'
  }
  return 'Gratis'
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`;
  }
  return 'Gratis';
}

const isBookingInProgress = (booking) => {
  const today = moment().format('YYYY-MM-DD')
  if (booking.date !== today) return false
  const now = moment()
  const startTime = moment(booking.time_from, 'HH:mm')
  return now.isSameOrAfter(startTime)
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
  { label: 'Pend. devolución', value: 6 },
]
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class=" row px-4 pt-2  md:justify-center items-center md:w-full md:px-24"
      style="height: 20%; overflow:hidden" >
      <div class="flex items-center col-12  col-md-12 pr-2 md:pl-8">
        <q-btn color="primary" unelevated class="flex-1 createBookingButton"
          style="border-radius: 0.5rem;" @click="goTo('/client/reserves/form/add')">
          <div class="flex items-center py-1 md:py-2">
            <q-icon name="eva-plus-outline" />
            <div class="q-pt-none text-bold pl-1">
              Agregar reserva
            </div>
          </div>
        </q-btn>
      </div>
      <div class="w-full flex justify-end col-12 md:px-5  pt-1">
        <div class="flex justify-between  md:px-5 w-full">
          <div class="flex q-gutter-xs py-1" style="overflow-x: auto; white-space: nowrap;">
            <q-btn dense outline no-caps class="px-2" size="sm"
              :class="{ 'text-primary text-bold': activeQuickFilter === 'all' }"
              @click="setQuickFilter('all')">Todas</q-btn>
            <q-btn dense outline no-caps class="px-2" size="sm"
              :class="{ 'text-primary text-bold': activeQuickFilter === 'upcoming' }"
              @click="setQuickFilter('upcoming')">Próximas</q-btn>
            <q-btn dense outline no-caps class="px-2" size="sm"
              :class="{ 'text-primary text-bold': activeQuickFilter === 'pending' }"
              @click="setQuickFilter('pending')">Pendientes</q-btn>
            <q-btn dense outline no-caps class="px-2" size="sm"
              :class="{ 'text-primary text-bold': activeQuickFilter === 'success' }"
              @click="setQuickFilter('success')">Exitosas</q-btn>
            <q-btn dense outline no-caps class="px-2" size="sm"
              :class="{ 'text-primary text-bold': activeQuickFilter === 'cancelled' }"
              @click="setQuickFilter('cancelled')">Canceladas</q-btn>
            
            <div class="w-px h-4 bg-gray-300 mx-1 self-center" v-if="authStore.user?.rol_id === 2"></div>
            
          </div>
          <q-btn outline color="primary" size="sm" @click="filterModal = true" class="filter-btn">
            <q-icon name="eva-funnel-outline" size="1.2rem" />
            <q-badge v-if="activeFilterCount > 0" color="primary" floating>{{ activeFilterCount }}</q-badge>
          </q-btn>
        </div>
      </div>
      <div class="w-full flex col-12">
            <q-checkbox 
              v-if="authStore.user?.rol_id === 2" 
              dense 
              v-model="userFilters.only_residents" 
              label="Reserva de residentes" 
              color="primary" 
              size="sm"
              class="text-primary self-center"
              @update:model-value="page = 1; getReserves()" 
            />
      </div>
    </div>
    <div class="pt-2" style="height: 80%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />

      </div>

      <!-- Content -->
      <div v-else class="px-4 pb-6 md:px-28">
    

        <!-- Lista de reservas -->
        <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
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
                    <span v-if="reserve.user_id !== authStore.user?.id" class="text-primary font-medium ml-1">
                      (Reserva de {{ reserve.user?.name }})
                    </span>
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
                      <img :src="urlMedia + '/images/icons/' + (reserve.comun_area?.icon || 'default') " alt=""
                        style="height:100%" :class="{'p-2': reserve.comun_area.name.includes('Sauna')}">
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
                          :data-reserve="reserve.id" v-if="[1, 2, 3].includes(reserve.status) && !isBookingInProgress(reserve)">
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

          <!-- Paginación -->
          <div v-if="lastPage > 1" class="flex justify-center mt-4">
            <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
              @update:model-value="getReserves()" />
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
    <q-dialog v-model="filterModal" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
      <q-card class="dialog_document public">
        <div class="header-sectionModal" style="border-bottom: 1px solid lightgray;">
          <div class="flex justify-between items-center pr-5 pl-2 py-2">
            <q-btn round outline icon="eva-arrow-back-outline" color="primary" @click="filterModal = false" />
            <div class="text-2xl text-primary font-bold pt-1">
              Filtrar
            </div>
          </div>
        </div>
        <div class="content-sectionModal">
          <section class="content__modalSectionRifa md:mt-0 mt-0 py-2">
            <div class="row py-4 px-5">
              <div class="mb-3 text-lg font-medium text-primary col-12">
                Visibilidad
              </div>
              <div class="col-12 my-1">
                <q-checkbox v-model="userFilters.hideCanceled"  color="primary" label="Ocultar canceladas" />
              </div>
              <div class="col-12 my-1">
                <q-checkbox v-model="userFilters.hidePast"  color="primary" label="Solo próximas (Fechas iguales o mayores a hoy)" />
              </div>
              <div class="col-12 my-1" v-if="authStore.user?.rol_id === 2">
                <q-checkbox v-model="userFilters.only_residents"  color="primary" label="Incluir reservas de mis inquilinos/huéspedes" />
              </div>
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
              <div class="mb-3 text-lg font-medium text-primary col-12">
                Estado
              </div>
              <div class="col-12">
                <q-select
                  class="form__inputsFilterBookings"
                  v-model="userFilters.status"
                  :options="statusOptions"
                  option-label="label"
                  option-value="value"
                  emit-value map-options
                  label="Selecciona un estado"
                  dense borderless />
              </div>
            </div>
          </section>
          <section class="pb-5">
            <div class="flex justify-evenly mt-2">
              <q-btn label="Limpiar" unelevated class="q-mx-sm" color="primary" outline style="border-radius: 0.8rem; padding:0px 2rem!important; font-size: 1rem;" @click="resetFilters()" />
              <q-btn label="Aplicar" unelevated class="q-mx-sm" color="primary" style="border-radius: 0.8rem; padding:0px 2rem!important; font-size: 1rem;" @click="page = 1; getReserves()" v-close-popup />
            </div>
          </section>
        </div>
      </q-card>
    </q-dialog>
   
    <template v-if="Object.values(selectedReserve).length > 0">
      <cancelReserveModal :dialog="(dialog == 'cancel')" :reserve="selectedReserve" @closeModal="dialog = ''"
        @updateList="getReserves()" />
    </template>
  </div>
</template>

<style scoped lang="scss">
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
  background-color: #2d6fb5;
  width: 100%;
  background-repeat: no-repeat;
  background-size: cover;

  transition: all 0.7s ease-in-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.03);
  }
}
</style>

<style lang="scss">
.header-sectionModal {
  height: 8%;
  overflow: hidden;
}
.content-sectionModal {
  height: 92%;
  overflow: auto;
}
.filterDialog {
  margin-left: 0%;
  overflow: hidden;
  position: relative;
  & .dialog_document {
    width: 100%;
    border-radius: 0rem !important;
    height: 100%;
    overflow: hidden;
  }
  & .q-dialog__inner--minimized {
    padding: 0px;
  }
}
.form__inputsFilterBookings {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
@media (max-width: 780px) {
  .form__inputsFilterBookings {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
  .filterDialog {
    & .dialog_document {
      max-height: 100% !important;
    }
  }
}
</style>