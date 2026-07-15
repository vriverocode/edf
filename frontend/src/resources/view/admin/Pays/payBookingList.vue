<script setup>
import { ref, onMounted } from 'vue'
import { usePayStore } from '@/services/store/pay.store'
import { useRouter } from 'vue-router'
import moment from 'moment'

const pays = ref([])
const loading = ref(true)
const payStore = usePayStore()
const router = useRouter()
const pagination = ref({
  page: 1,
  lastPage: 1,
  perPage: 20,
})

const getPays = (page = 1) => {
  loading.value = true
  payStore.getPaysByUser({ type: 2, paginate: pagination.value.perPage, page })
    .then((response) => {
      pays.value = response.data.data || []
      pagination.value.lastPage = response.data.last_page || 1
      pagination.value.page = response.data.current_page || 1
    })
    .catch(() => {})
    .finally(() => {
      loading.value = false
    })
}

const goToCreate = () => {
  router.push('/admin/pay/register')
}

const goToDetail = (id) => {
  router.push(`/admin/pay/validate/${id}`)
}

const onPageChange = (page) => {
  getPays(page)
}

onMounted(() => {
  getPays()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="reserve-list-footer px-4 flex justify-center items-center md:w-full md:px-12"
      style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem; width: 100%;" @click="goToCreate()">
        <div class="flex items-center py-2">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Registrar pago
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
        <!-- Lista de pagos -->
        <div v-if="pays.length > 0" class="space-y-3 md:px-5">
          <div v-for="pay in pays" :key="pay.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 cursor-pointer"
            style="position: relative;" @click="goToDetail(pay.id)">

            <div class="px-4 pb-4 pt-2">
              <div class="mb-2">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-2">
                    {{ pay.booking?.comun_area?.name || 'Reserva' }}
                  </h3>
                  <div class="text-sm text-gray-500">
                    {{ pay.user?.name || '—' }}
                  </div>
                </div>
                <span :class="'bg-' + pay.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgeReserve">
                  {{ pay.status_label }}
                </span>
              </div>

              <div class="flex items-center space-x-4">
                <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0">
                  <div class="boxItem_list_v2">
                    <div class="flex justify-center items-center h-full w-full bg-blue-50 text-blue-500" style="border-radius: 0.8rem;">
                      <q-icon name="eva-calendar-outline" size="2rem" />
                    </div>
                  </div>
                </div>

                <div class="flex-1 space-y-2">
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">S/. {{ pay.amount }}</span>
                  </div>
                  <div class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="font-medium">Pagado: {{ moment(pay.pay_date).format('DD MMM YYYY') }}</span>
                  </div>
                  <div v-if="pay.booking" class="flex items-center text-sm text-gray-700">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">{{ moment(pay.booking.date).format('DD MMM YYYY') }} {{ pay.booking.time_from }} - {{ pay.booking.time_to }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Paginación -->
        <div v-if="pagination.lastPage > 1" class="flex justify-center mt-4">
          <q-pagination
            v-model="pagination.page"
            :max="pagination.lastPage"
            :max-pages="6"
            boundary-numbers
            direction-links
            @update:model-value="onPageChange"
          />
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay pagos de reservas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no se han registrado pagos de reservas de áreas comunes.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
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
