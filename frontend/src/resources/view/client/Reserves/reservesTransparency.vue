<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useReserveStore } from '@/services/store/reserve.store'
import { useComunAreaStore } from '@/services/store/comunArea.store'
import { useUserStore } from '@/services/store/users.store'
import iconsApp from '@/assets/icons/index'
import moment from 'moment'
import { Notify } from 'quasar'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const route = useRoute()
const reserveStore = useReserveStore()
const comunAreaStore = useComunAreaStore()
const userStore = useUserStore()

const reserves = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const exporting = ref(false)
const showFilter = ref(false)
const areas = ref([])
const userOptions = ref([])

const filters = ref({
  status: -1,
  area_id: null,
  date_from: null,
  date_to: null,
  user_id: null,
  sort_by: 'created_at',
  sort_dir: 'desc',
})

const localFilters = ref({ ...filters.value })

const statusOptions = [
  { label: 'Todos', value: -1 },
  { label: 'Cancelada', value: 0 },
  { label: 'Pago pendiente', value: 1 },
  { label: 'Pendiente de aprob.', value: 2 },
  { label: 'Exitoso', value: 3 },
  { label: 'Completada', value: 4 },
  { label: 'Pend. reembolso', value: 5 },
  { label: 'Pend. devolución', value: 6 },
]

const sortOptions = [
  { label: 'Fecha de creación', value: 'created_at' },
  { label: 'Fecha reserva', value: 'date' },
  { label: 'Estado', value: 'status' },
  { label: 'Monto', value: 'amount' },
]

const sortDirOptions = [
  { label: 'Descendente', value: 'desc' },
  { label: 'Ascendente', value: 'asc' },
]

const hasActiveFilter = computed(() => {
  return filters.value.status !== -1
    || filters.value.area_id !== null
    || filters.value.date_from !== null
    || filters.value.date_to !== null
    || filters.value.user_id !== null
})

const activeFilterCount = computed(() => {
  let count = 0
  if (filters.value.status !== -1) count++
  if (filters.value.area_id !== null) count++
  if (filters.value.date_from !== null) count++
  if (filters.value.date_to !== null) count++
  if (filters.value.user_id !== null) count++
  return count
})

const getReserves = () => {
  loading.value = true
  page.value = 1
  reserveStore.getAllBookings({
    page: page.value,
    per_page: 10,
    ...filters.value,
  })
    .then((response) => {
      const payload = response.data || {}
      reserves.value = payload.data || payload || []
      lastPage.value = payload.last_page || 1
    })
    .catch(() => {
      reserves.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

const getPaymentAmount = (booking) => {
  if (booking.amount > 0) return `S/. ${booking.amount}`
  return 'Gratis'
}

const getDepartmentNumber = (booking) => {
  return booking.departament?.number || booking.user?.units?.[0]?.number || '—'
}

const formatTime = (time) => {
  if (!time) return ''
  return time.substring(0, 5)
}

const loadAreas = () => {
  comunAreaStore.getAllComunAreas()
    .then((data) => {
      if (data.code !== 200) return
      areas.value = (data.data || []).map(a => ({ label: a.name, value: a.id }))
    })
    .catch(() => { areas.value = [] })
}

const loadUsers = () => {
  userStore.getUsersOptions()
    .then((data) => {
      if (data.code !== 200) return
      userOptions.value = (data.data || []).map(u => ({ label: u.name, value: u.id }))
    })
    .catch(() => { userOptions.value = [] })
}

const openFilter = () => {
  localFilters.value = { ...filters.value }
  showFilter.value = true
}

const applyFilters = () => {
  filters.value = { ...localFilters.value }
  page.value = 1
  getReserves()
  showFilter.value = false
}

const resetFilters = () => {
  localFilters.value = {
    status: -1,
    area_id: null,
    date_from: null,
    date_to: null,
    user_id: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
  }
}

const handleExport = () => {
  exporting.value = true
  reserveStore.exportBookings(filters.value)
    .then(() => {
      Notify.create({ color: 'positive', message: 'Archivo descargado correctamente', timeout: 2000 })
    })
    .catch((error) => {
      Notify.create({ color: 'negative', message: error || 'Error al exportar', timeout: 2000 })
    })
    .finally(() => {
      exporting.value = false
    })
}

onMounted(() => {
  getReserves()
  loadAreas()
  loadUsers()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: auto;">

      <!-- Barra filtros (solo admin/seguridad) -->
      <div
        class="flex justify-end items-center gap-2 px-4 pt-4 md:px-28">
        <q-btn :color="hasActiveFilter ? 'primary' : 'grey-7'" outline
          :label="hasActiveFilter ? `Filtros (${activeFilterCount})` : 'Filtros'"
          @click="openFilter">
          <q-badge v-if="hasActiveFilter" color="red" floating rounded>{{ activeFilterCount }}</q-badge>
        </q-btn>
        <q-btn v-if="2==1" color="green" unelevated label="Exportar Excel" icon="eva-download-outline"
          :loading="exporting" @click="handleExport" />
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Lista -->
      <div v-else class="px-4 py-6 md:px-28">
        <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
          <div v-for="reserve in reserves" :key="reserve.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;">

            <div class="pb-4 pt-2 border-b border-dashed border-gray-300">
              <div class="flex justify-between items-start mb-2">
                <span :class="'bg-' + reserve.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ reserve.status_label }}
                </span>
              </div>

              <div>
                <div class="flex px-3 col-6 items-center text-sm text-gray-700 pb-1" style="border-bottom: 1px solid lightgrey;">
                  <div class="text-h6 font-bold">{{ reserve.comun_area?.name || '—' }}</div>
                </div>
              </div>
              <div class="flex items-center px-4 w-full">
                <div class="row w-full pt-4">
                  <div class="flex col-6 col-md-3 items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                  </div>
                  <div class="flex col-6 col-md-3 items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium">
                      {{ formatTime(reserve.time_from) }} - {{ formatTime(reserve.time_to) }}
                    </span>
                  </div>
                  <div class="flex col-6 col-md-3 items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-1 text-gray-500 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                    <span class="font-medium">Dpto: {{ getDepartmentNumber(reserve) }}</span>
                  </div>
                  <div class="flex col-6 col-md-3 items-center text-sm text-gray-700 pt-2">
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
                  <span class="text-sm font-medium text-gray-700">{{ reserve.user?.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="flex justify-center mt-4">
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
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay reservas</h3>
          <p class="text-gray-600 text-center mb-6">No se encontraron reservas en el sistema.</p>
        </div>
      </div>

      <!-- Modal de filtros -->
      <q-dialog v-model="showFilter" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
        <q-card class="dialog_document">
          <div class="header-sectionModal" style="border-bottom: 1px solid lightgray">
            <div class="flex justify-between items-center pr-5 pl-2 py-2">
              <q-btn round outline icon="eva-arrow-back-outline" color="primary" @click="showFilter = false" />
              <div class="text-2xl text-primary font-bold pt-1">Filtrar</div>
            </div>
          </div>

          <div class="content-sectionModal">
            <section class="content__modalSectionRifa py-2">
              <!-- Estado -->
              <div class="row pt-3 pb-4 px-5">
                <div class="mb-1 text-lg font-medium text-primary col-12">Estado</div>
                <div class="col-12">
                  <q-select v-model="localFilters.status" :options="statusOptions"
                    emit-value map-options dense borderless class="form__inputsFilterBookings" />
                </div>
              </div>

              <!-- Área común -->
              <div class="row pt-4 pb-5 px-5" style="border-top: 1px solid lightgray">
                <div class="mb-3 text-lg font-medium text-primary col-12">Área común</div>
                <div class="col-12">
                  <q-select v-model="localFilters.area_id" :options="areas"
                    emit-value map-options use-input input-debounce="200"
                    label="Selecciona un área" clearable dense borderless class="form__inputsFilterBookings" />
                </div>
              </div>

              <!-- Rango de fechas -->
              <div class="row pt-4 pb-5 px-5" style="border-top: 1px solid lightgray">
                <div class="mb-4 text-lg font-medium text-primary col-12">Rango de fechas</div>
                <div class="col-6 pr-1">
                  <q-input v-model="localFilters.date_from" dense borderless type="date" label="Desde"
                    class="form__inputsFilterBookings" />
                </div>
                <div class="col-6 pl-1">
                  <q-input v-model="localFilters.date_to" dense borderless type="date" label="Hasta"
                    class="form__inputsFilterBookings" />
                </div>
              </div>

              <!-- Usuario -->
              <div class="row pt-4 pb-5 px-5" style="border-top: 1px solid lightgray">
                <div class="mb-3 text-lg font-medium text-primary col-12">Usuario</div>
                <div class="col-12">
                  <q-select v-model="localFilters.user_id" :options="userOptions"
                    emit-value map-options use-input input-debounce="200"
                    label="Selecciona un usuario" clearable dense borderless class="form__inputsFilterBookings" />
                </div>
              </div>

              <!-- Ordenar por -->
              <div class="row py-4 px-5" style="border-top: 1px solid lightgray">
                <div class="mb-4 text-lg font-medium text-primary col-12">Ordenar por</div>
                <div class="col-6 pr-1">
                  <q-select v-model="localFilters.sort_by" :options="sortOptions"
                    emit-value map-options dense borderless class="form__inputsFilterBookings" />
                </div>
                <div class="col-6 pl-1">
                  <q-select v-model="localFilters.sort_dir" :options="sortDirOptions"
                    emit-value map-options dense borderless class="form__inputsFilterBookings" />
                </div>
              </div>
            </section>

            <section class="pb-5">
              <div class="flex justify-evenly mt-2">
                <q-btn label="Restablecer" unelevated class="q-mx-sm" color="primary" outline
                  style="border-radius: 0.8rem; padding: 0px 2rem; font-size: 1rem"
                  @click="resetFilters" />
                <q-btn label="Aplicar" unelevated class="q-mx-sm" color="primary"
                  style="border-radius: 0.8rem; padding: 0px 2rem; font-size: 1rem"
                  @click="applyFilters" />
              </div>
            </section>
          </div>
        </q-card>
      </q-dialog>

    </div>
  </div>
</template>

<style lang="scss">
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}

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
