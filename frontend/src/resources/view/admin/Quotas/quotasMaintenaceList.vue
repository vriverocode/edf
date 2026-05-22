<script setup>
import { ref, onMounted } from 'vue';
import { usePayStore } from '@/services/store/pay.store';
import { useRouter } from 'vue-router';
import moment from 'moment';
import iconsApp from '@/assets/icons/index'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const pays = ref([]);
const loading = ref(true);
const payStore = usePayStore();
const router = useRouter();

// Por defecto solo cuotas
const filters = ref({
  type: 1, 
  sort_by: 'created_at',
  sort_dir: 'desc'
});

const getPays = () => {
  loading.value = true;
  payStore.getPaysByUser(filters.value)
    .then((response) => {
      if (response.code !== 200) throw response;
      pays.value = response.data;
    })
    .catch((response) => {
      console.log(response);
    })
    .finally(() => {
      loading.value = false;
    });
}

const goToValidate = (pay) => {
  router.push('/admin/pay/validate/' + pay.id);
}

const getTitlePay = (pay) => {
  const month = pay.quotas && pay.quotas.length > 0 ? pay.quotas[0].month_label : '';
  return `${pay.title_pay} ${month}`;
}

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
}

onMounted(() => {
  getPays();
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
        <div v-if="pays.length > 0" class="space-y-3 md:px-5">
          <div v-for="pay in pays" :key="pay.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 cursor-pointer quotaItem"
            style="position: relative;" @click="goToValidate(pay)">

            <!-- Sección superior - Detalles del pago -->
            <div class="px-4 pb-4 pt-2">
              <!-- Header con título y badge -->
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-1 titleQuota">
                    {{ getTitlePay(pay) }}
                    <span class="text-sm font-normal text-gray-600 block" v-if="pay.user">
                       A nombre de: {{ pay.user.name }}
                    </span>
                    <span class="text-sm font-normal text-gray-600 block" v-if="pay.quotas && pay.quotas.length > 0">
                       Unidad: {{ pay.quotas[0].departament?.number }}
                    </span>
                    <!-- Badge "New" opcional -->
                    <span v-if="pay.created_at && moment(pay.created_at).isAfter(moment().subtract(2, 'days'))"
                      class="absolute top-2 right-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded-md">
                      Nuevo
                    </span>
                  </h3>
                </div>
              </div>

              <!-- Contenido principal con detalle -->
              <div class="space-y-2 pt-3">
                <!-- Monto -->
                <div class="flex items-center text-sm text-gray-700">
                  <div v-html="iconsApp.moneyIcon" class="mr-2 w-4 h-4" />
                  <span class="font-medium">S/. {{ pay.amount }}</span>
                </div>

                <!-- Fecha de pago -->
                <div class="flex items-center text-sm text-gray-700">
                  <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                    </path>
                  </svg>
                  <span class="font-medium">Fecha de pago: {{ formatDate(pay.pay_date) }}</span>
                </div>
              </div>
            </div>

            <!-- Sección inferior - Acciones -->
            <div class="px-4 py-3 bg-gray-50 border-t border-gray-200">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <!-- Icono de estado -->
                  <q-icon :name="pay.status_icon" :color="pay.status_color" size="1.5rem" />
                  <span class="ml-2 text-sm font-medium text-gray-700">{{ pay.status_label }}</span>
                </div>
                <div class="flex items-center">
                  <q-btn unelevated rounded color="warning" size="sm" class="ml-3" v-if="pay.status == 1 || pay.status == 2">
                    <q-tooltip class="bg-primary text-white text-body2" :offset="[10, 10]">
                      Validar pago
                    </q-tooltip>
                    <div v-html="iconsApp.processPay"></div>
                  </q-btn>
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
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay pagos de cuotas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no se ha realizado ningún pago de cuota.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.quotaItem {
  transition: all ease 0.5s;

  &:hover {
    opacity: 0.7;
  }
}

.titleQuota {
  transition: all ease 1s;

  &:hover {
    text-decoration: underline;
  }
}
</style>