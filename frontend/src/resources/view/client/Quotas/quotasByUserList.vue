<script setup>
import { ref, onMounted } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';
import cuotas from '@/assets/img/menu/cuotas.png'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const quotas = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();
const router = useRouter();
const dialog = ref(false);
const filters = ref({
  status: 4,
  quota_method: '',
  type: '',
  date_from: '',
  date_to: '',
  sort_by: 'created_at',
  sort_dir: 'desc'
});

const getQuotas = () => {
  loading.value = true;
  quotaStore.getQuotasByUser()
    .then((response) => {
      if (response.code !== 200) throw response;
      quotas.value = response.data;
    })
    .catch((response) => {
      console.error(response);
    })
    .finally(() => {
      loading.value = false;
    });
}

const goTo = (quota) => {
  if (quota.status == 2 || quota.status == 3) {
    const payId = quota.pay || (quota.details && quota.details[0] && quota.details[0].pays && quota.details[0].pays[0] && quota.details[0].pays[0].id)
    if (payId) {
      router.push(`/client/pay/quotas/view/${payId}`)
    }
    return
  }
  const ids = quota.details
    ? quota.details.map(d => d.id)
    : [quota.id]
  router.push({
    path: `/client/quota/pay/${ids[0]}`,
    query: ids.length > 1 ? { quota_ids: ids.join(',') } : {}
  })
}

const showDialog = () => {
  dialog.value = true;
}

const updateFilters = (newFilters) => {
  filters.value = { ...newFilters };
  getQuotas();
}

const closeModal = () => {
  dialog.value = false;
}

const getMonthName = (monthNumber) => {
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  return months[monthNumber - 1] || '';
}

const hasTenantPays = (quota) => {
  if (!quota.details || !quota.details.length) return false
  return quota.details.some(d => d.departament?.tenant_pays_quota === true)
}

const getTitleQuota = (quota) => {
  // Utilizamos el mes que viene en la agrupación
  return 'Mensualidad: ' + getMonthName(quota.month);
}

const getStatusInfo = (status) => {
  if (status === 1) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente' };
  }
  if (status === 2) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente de aprob.' };
  }
  if (status === 3) {
    return { color: 'positive', icon: 'eva-checkmark-circle-2-outline', label: 'Pagado' };
  }
  return { color: 'negative', icon: 'eva-checkmark-circle-2-outline', label: 'Rechazado' };
}

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
}

onMounted(() => {
  getQuotas();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 py-6 md:px-28">
        <div v-if="quotas.length > 0" class="space-y-5 md:px-5">
          <div v-for="quota in quotas" :key="quota.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative; border: 1px solid lightgrey">

            <div class="px-4 pb-2 pt-2 md:pt-4">
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-1">
                    {{ getTitleQuota(quota) }}
                    <span v-if="hasTenantPays(quota)"
                      class="ml-2 inline-flex items-center bg-amber-100 text-amber-800 text-xs font-semibold px-2 py-0.5 rounded-md">
                      <q-icon name="eva-person-outline" size="0.85rem" class="mr-0.5" />
                      Inquilino paga
                    </span>
                    <span v-if="quota.created_at && moment(quota.created_at).isAfter(moment().subtract(7, 'days'))"
                      class="absolute top-2 right-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded-md">
                      Nuevo
                    </span>
                  </h3>
                  <div class="text-xs text-gray-500 mb-2">{{ quota.description }}</div>
                </div>
              </div>

              <div class="space-y-2 pt-3">
                <div class="row items-center ">
                  <div class="flex items-center text-sm text-gray-700 col-5 col-md-4 ">
                    <svg class="w-5 h-5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                      </path>
                    </svg>
                    <span class="font-medium text-base">S/. {{ Number(quota.amount).toFixed(2) }}</span>
                  </div>
                  <div class="flex items-cente
                  r text-sm text-gray-700 col-7 col-md-8 justify-end md:justify-start">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Límite: {{ formatDate(quota.due_date) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="px-4 py-2 md:py-3 border-t cursor-pointer" :class="`bg-${getStatusInfo(quota.status).color}`"
              @click="goTo(quota)">
              <div class="flex justify-center items-center">
                <div class="flex items-center">
                  <q-icon :name="getStatusInfo(quota.status).icon" color="white" size="1.5rem" />
                  <span class="ml-1 text-sm font-medium text-white">{{ getStatusInfo(quota.status).label }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
         <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <img :src="cuotas" class="md:w-auto h-3/5"/>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">¡No tienes cuotas pendientes! 🎉</h3>
          <p class="text-gray-600 text-center mb-6"> Estás al día con tus pagos. Gracias por contribuir al mantenimiento y buen funcionamiento del edificio.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Estilos adicionales si es necesario */
</style>