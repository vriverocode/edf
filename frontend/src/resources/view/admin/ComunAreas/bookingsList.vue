<script setup>
import { useReserveStore } from '@/services/store/reserve.store';
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
const userStore = useUserStore()
const reserves = ref([])
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

const onChangeFilter = () => {
  page.value = 1
  getReservesByArea()
}

onMounted(() => {
  getReservesByArea()
})

</script>
<template>
  <div class="h-full">
    <div class="px-4 py-3 md:px-28 flex items-center q-col-gutter-sm" style="height: 10%;">
      <div class="row items-center w-full">
        <div class="col-4 col-md-2 pr-2">
          <q-select dense borderless class="form__inputsR" v-model="selectedMonth" :options="monthOptions"
            option-label="name" option-value="value" emit-value map-options @update:model-value="onChangeFilter" />
        </div>
        <div class="col-4 col-md-2 pr-2">
          <q-input dense borderless class="form__inputsR" type="number" v-model.number="selectedYear"
            @update:model-value="onChangeFilter" />
        </div>
        <div class="col-4 col-md-3">
          <q-checkbox v-model="showCancelled" label="Mostrar canceladas" @update:model-value="onChangeFilter" />
        </div>
      </div>
    </div>

    <div class="" style="height: 80%; overflow: auto;">

      <!-- Loading State -->
      <div v-if="!readyPage" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 md:px-28">
        <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;">

            <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
              <div class="flex justify-between items-start mb-2">
                <span :class="'bg-' + reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <div class="flex items-center space-x-4">
                <div class="flex-1 space-y-2">
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>

                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTime(reserve.time_from) }} - {{ formatTime(reserve.time_to) }}
                    </span>
                  </div>

                  <div class="flex items-center text-sm text-gray-700">
                    <q-icon name="eva-home-outline" size="sm" class="q-mr-xs" />
                    <span class="font-medium">Dpto: {{ getDepartmentNumber(reserve) }}</span>
                  </div>

                  <div class="flex items-center text-sm text-gray-700">
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
    <div class="px-4 pb-12 md:pb-8 md:flex md:justify-center items-center md:w-full md:px-12" style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem;" @click="openCreateReserve">
        <div class="flex items-center py-2">
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
<style scoped>
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
