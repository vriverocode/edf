<script setup>
import { useReserveStore } from '@/services/store/reserve.store';
import { useMaintenanceStore } from '@/services/store/maintenance.store'
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
    '_'
  ),
})

const reserveStore = useReserveStore()
const maintenanceStore = useMaintenanceStore()
const userStore = useUserStore()
const reserves = ref([])
const maintenances = ref([])
const readyPage = ref(false)
const router = useRouter()
const route = useRoute()
const page = ref(1)
const lastPage = ref(1)
const now = new Date()
const selectedMonth = ref(now.getMonth() + 1)
const selectedYear = ref(now.getFullYear())
const showCancelled = ref(false)

const monthOptions = [
  { value: 1, name: 'Enero' },
  { value: 2, name: 'Febrero' },
  { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' },
  { value: 5, name: 'Mayo' },
  { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' },
  { value: 8, name: 'Agosto' },
  { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' },
  { value: 11, name: 'Noviembre' },
  { value: 12, name: 'Diciembre' }
]

const ownerDialog = ref(false)
const ownerSearch = ref('')
const ownerOptions = ref([])
const selectedOwner = ref(null)
const loadingOwners = ref(false)

const openCreateReserve = () => {
  ownerDialog.value = true
  selectedOwner.value = null
  ownerSearch.value = ''
  loadOwners()
}

const loadOwners = async () => {
  loadingOwners.value = true
  try {
    const res = await userStore.getUsers({ rol: 2, per_page: 999 })
    ownerOptions.value = (res?.data?.data || res?.data || []).map(u => ({
      label: `${u.name} — ${u.email}`,
      value: u.id
    }))
  } catch {
    ownerOptions.value = []
  } finally {
    loadingOwners.value = false
  }
}

const confirmOwner = () => {
  if (!selectedOwner.value) return
  ownerDialog.value = false
  router.push(`/client/reserves/form/add?user_id=${selectedOwner.value}&area_id=${route.params.id}`)
}

const getReservesByArea = () => {
  readyPage.value = false
  const params = {
    page: page.value,
    per_page: 10,
    date_from: `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-01`,
    date_to: `${selectedYear.value}-${String(selectedMonth.value).padStart(2, '0')}-31`,
  }
  if (!showCancelled.value) params.status = 1
  reserveStore.getReservesByArea(route.params.id, params)
    .then((response) => {
      const payload = response.data || {}
      reserves.value = payload.data || payload || []
      lastPage.value = payload.last_page || payload.pagination?.last_page || 1
      setTimeout(() => {
        readyPage.value = true
      }, 100)
    })
    .catch(() => {
      reserves.value = []
      readyPage.value = true
    })
}
const goTo = (url) => {
  router.push(url)
}

const formatTime = (time) => {
  return time
}

const getPaymentStatus = (booking) => {
  if (booking.amount > 0) {
    return !booking.pay
      ? 'No pagada'
      : booking.pay.status == 1
        ? 'Pendiente de aprobación'
        : 'Pagado'
  }
  return 'Confirmado'
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) {
    return `S/. ${booking.amount}`
  }
  return 'Gratis'
}

const getDepartmentNumber = (booking) => {
  return booking.department?.number || booking.user?.units?.[0]?.number || '—'
}

const loadMaintenances = async () => {
  try {
    const res = await maintenanceStore.getMaintenanceByArea(route.params.id, '')
    maintenances.value = Array.isArray(res) ? res : []
  } catch {
    maintenances.value = []
  }
}

const hasMaintenanceOnDate = (date) => {
  return maintenances.value.some(m => m.date === date)
}

const onChangeFilter = () => {
  page.value = 1
  getReservesByArea()
}

onMounted(() => {
  getReservesByArea()
  loadMaintenances()
})

</script>
<template>
  <div class="h-full">
    <div class="px-4 py-3 md:px-28 flex items-center q-col-gutter-sm" style="height: 17%;">
      <div class="row items-center w-full">
        <div class="col-6 col-md-2 pr-2">
          <q-select dense borderless class="form__inputsR" v-model="selectedMonth" :options="monthOptions"
            option-label="name" option-value="value" emit-value map-options @update:model-value="onChangeFilter" />
        </div>
        <div class="col-6 col-md-2 pr-2">
          <q-input dense borderless class="form__inputsR" type="number" v-model.number="selectedYear"
            @update:model-value="onChangeFilter" />
        </div>
        <div class="col-6 col-md-3 pt-2 md:pt-0">
          <q-checkbox v-model="showCancelled" label="Mostrar canceladas" @update:model-value="onChangeFilter" />
        </div>
      </div>
    </div>

    <div class="" style="height: 73%; overflow: auto;">

      <!-- Loading State -->
      <div v-if="!readyPage" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 md:px-28">
        <!-- Maintenance Banner -->
        <div v-if="maintenances.length > 0" class="q-mb-md">
          <q-banner class="bg-orange-2 text-orange-9 rounded-lg" inline-actions>
            <template v-slot:avatar>
              <q-icon name="eva-alert-triangle-outline" color="orange" size="md" />
            </template>
            <span class="text-weight-medium">Mantenimiento programado</span>
            <div class="text-caption">
              <span v-for="(m, i) in maintenances" :key="m.id">
                {{ moment(m.date).format('DD/MM') }}
                <template v-if="m.time_from && m.time_to">{{ m.time_from }}-{{ m.time_to }}</template>
                <template v-else>Todo el día</template><span v-if="i < maintenances.length - 1">, </span>
              </span>
            </div>
          </q-banner>
        </div>
        <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;"
            :class="{ 'border-orange-4': hasMaintenanceOnDate(reserve.date) }">

            <div class="pb-4 pt-2 border-b border-dashed border-gray-300">
              <div class="flex justify-between items-start mb-2">
                <span :class="'bg-' + reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <div>
                <div class="flex px-3 col-6 items-center text-sm text-gray-700 pb-1" style="border-bottom: 1px solid lightgrey;">
                    <svg class="text-gray-500" style="transform: translateX(-3px); margin-right:1px" width="30px" height="30px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <path d="M10.5 6H9.5C9.22386 6 9 6.22386 9 6.5V7.5C9 7.77614 9.22386 8 9.5 8H10.5C10.7761 8 11 7.77614 11 7.5V6.5C11 6.22386 10.7761 6 10.5 6Z" fill="#4f4f4f"></path> <path d="M14.5 6H13.5C13.2239 6 13 6.22386 13 6.5V7.5C13 7.77614 13.2239 8 13.5 8H14.5C14.7761 8 15 7.77614 15 7.5V6.5C15 6.22386 14.7761 6 14.5 6Z" fill="#4f4f4f"></path> <path d="M10.5 9.5H9.5C9.22386 9.5 9 9.72386 9 10V11C9 11.2761 9.22386 11.5 9.5 11.5H10.5C10.7761 11.5 11 11.2761 11 11V10C11 9.72386 10.7761 9.5 10.5 9.5Z" fill="#4f4f4f"></path> <path d="M14.5 9.5H13.5C13.2239 9.5 13 9.72386 13 10V11C13 11.2761 13.2239 11.5 13.5 11.5H14.5C14.7761 11.5 15 11.2761 15 11V10C15 9.72386 14.7761 9.5 14.5 9.5Z" fill="#4f4f4f"></path> <path d="M10.5 13H9.5C9.22386 13 9 13.2239 9 13.5V14.5C9 14.7761 9.22386 15 9.5 15H10.5C10.7761 15 11 14.7761 11 14.5V13.5C11 13.2239 10.7761 13 10.5 13Z" fill="#4f4f4f"></path> <path d="M14.5 13H13.5C13.2239 13 13 13.2239 13 13.5V14.5C13 14.7761 13.2239 15 13.5 15H14.5C14.7761 15 15 14.7761 15 14.5V13.5C15 13.2239 14.7761 13 14.5 13Z" fill="#4f4f4f"></path> <path d="M18.25 19.25H17.75V4C17.7474 3.80189 17.6676 3.61263 17.5275 3.47253C17.3874 3.33244 17.1981 3.25259 17 3.25H7C6.80189 3.25259 6.61263 3.33244 6.47253 3.47253C6.33244 3.61263 6.25259 3.80189 6.25 4V19.25H5.75C5.55109 19.25 5.36032 19.329 5.21967 19.4697C5.07902 19.6103 5 19.8011 5 20C5 20.1989 5.07902 20.3897 5.21967 20.5303C5.36032 20.671 5.55109 20.75 5.75 20.75H18.25C18.4489 20.75 18.6397 20.671 18.7803 20.5303C18.921 20.3897 19 20.1989 19 20C19 19.8011 18.921 19.6103 18.7803 19.4697C18.6397 19.329 18.4489 19.25 18.25 19.25ZM16.25 19.25H11V17C11 16.8674 10.9473 16.7402 10.8536 16.6464C10.7598 16.5527 10.6326 16.5 10.5 16.5H9.5C9.36739 16.5 9.24021 16.5527 9.14645 16.6464C9.05268 16.7402 9 16.8674 9 17V19.25H7.75V4.75H16.25V19.25Z" fill="#4f4f4f"></path> </g></svg>
                    <div class="text-h6 font-bold">Dpto: {{ getDepartmentNumber(reserve) }}</div>
                  </div>
              </div>
              <div class="flex items-center space-x-4 px-4 ">
                <div class="row pt-4">
                  
                  <div class="flex col-6 items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>sss
                  </div>
                  <div class="flex col-6 items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTime(reserve.time_from) }} - {{ formatTime(reserve.time_to) }}
                    </span>
                  </div>
                  <div class="flex col-12 items-center text-sm text-gray-700 pt-2">
                    <div v-html="iconsApp.moneyIcon" />
                    <span class="font-medium">
                      {{ getPaymentAmount(reserve) }} -
                      <span class="font-medium">
                        {{ reserve.pay?.pay_method?.name ?? 'Confirmada' }}
                      </span>
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="p-4 bg-gray-50">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <span class="text-sm font-medium text-gray-700">{{ reserve.user.name }}</span>
                </div>
                <div class="flex items-center">
                  <q-btn unelevated rounded color="warning" size="sm" class="ml-3" v-if="reserve.status == 2"
                    @click="goTo('/admin/pay/validate/' + reserve.pay.id)">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Validar pago
                    </q-tooltip>
                    <div v-html="iconsApp.processPay"></div>
                  </q-btn>
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                    <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                      Información de reserva
                    </q-tooltip>
                    <div v-html="iconsApp.optionsBook"></div>
                    <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="goTo('/client/reserves/view/' + reserve.id)">
                          <q-item-section>Ver detalles</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup v-if="reserve.status == 2"
                          @click="goTo('/admin/pay/validate/' + reserve.pay.id)">
                          <q-item-section>Validar Pago</q-item-section>
                        </q-item>
                        <q-separator />
                      </q-list>
                    </q-menu>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-center mt-4">
            <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
              @update:model-value="getReservesByArea()" />
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
          <p class="text-gray-600 text-center mb-6">No se encontraron reservas para este período.</p>
        </div>
      </div>
    </div>
    <!-- Botón crear reserva -->
    <div class="px-4 pb-12 pt-3 md:pb-8 md:flex md:justify-center items-center md:w-full md:px-12" style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem;" @click="openCreateReserve">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">Agregar reserva</div>
        </div>
      </q-btn>
    </div>
  </div>
  <q-dialog v-model="ownerDialog" persistent>
    <q-card style="min-width: 320px; width: 95%; max-width: 480px;">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Seleccionar propietario</div>
      </q-card-section>
      <q-card-section>
        <q-input dense borderless v-model="ownerSearch" placeholder="Buscar propietario..."
          class="form__inputsR q-mb-sm" color="primary" clearable />
        <q-select dense borderless v-model="selectedOwner" :options="ownerOptions"
          option-label="label" option-value="value" emit-value map-options class="form__inputsTypeDepart"
          :loading="loadingOwners" :disable="loadingOwners" use-chips use-input
          @filter="(val, update) => { update(); }" />
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat no-caps label="Cancelar" color="grey-7" v-close-popup />
        <q-btn color="primary" no-caps label="Continuar" :disable="!selectedOwner" @click="confirmOwner" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>
<style >
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}

.form__inputsR .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
  .createBookingButton {
    width: auto;
  }

  @media (max-width: 780px) {
    .createBookingButton {
      width: 100%;
    }
  }
</style>
