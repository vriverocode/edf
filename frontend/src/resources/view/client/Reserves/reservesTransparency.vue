<script setup>
import { ref, onMounted } from 'vue'
import { useReserveStore } from '@/services/store/reserve.store'
import { useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import moment from 'moment'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const reserveStore = useReserveStore()
const router = useRouter()

const reserves = ref([])
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)

const getReserves = () => {
  loading.value = true
  reserveStore.getAllBookings({ page: page.value, per_page: 10 })
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

onMounted(() => {
  getReserves()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: auto;">
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
    </div>
  </div>
</template>

<style>
.badgeReserve {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>
