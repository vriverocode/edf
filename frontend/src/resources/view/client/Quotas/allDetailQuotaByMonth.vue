<script setup>
import { ref, onMounted, computed } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useRoute, useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';
import iconsApp from '@/assets/icons/index';
import voucherModal from '@/components/pay/voucherModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const quotas = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();
const voucherDialog = ref(false);
const activeVoucher = ref(null);

const route = useRoute();
const router = useRouter();

const isAdminRoute = computed(() => {
  return route.name === 'quotasDetailByMonthAdmin';
});

const pageTitle = computed(() => {
  const first = quotas.value[0];
  if (!first) return 'Cuotas del mes';
  const label = first.month_label ?? moment(first.due_date).format('MMMM');
  const year = route.query.year ?? moment(first.due_date).format('YYYY');
  return `Mensualidad: ${label} ${year}`;
});

const getDisplayPay = (quota) => {
  const pays = Array.isArray(quota.pays) ? quota.pays : [];
  if (!pays.length) return null;
  return (
    pays.find((p) => Number(p.status) === 1)
    ?? pays.find((p) => Number(p.status) === 2)
    ?? pays[0]
  );
};

const pendingPayForValidation = (quota) => {
  const pays = Array.isArray(quota.pays) ? quota.pays : [];
  return pays.find((p) => Number(p.status) === 1) ?? null;
};

const canValidateQuota = (quota) => {
  if (!isAdminRoute.value) return false;
  if (Number(quota.status) !== 2) return false;
  return pendingPayForValidation(quota) !== null;
};

const goToValidate = (quota) => {
  const pay = pendingPayForValidation(quota);
  if (pay?.id) {
    router.push(`/admin/pay/validate/${pay.id}`);
  }
};

const openVoucher = (pay, event) => {
  event?.stopPropagation();
  if (!pay?.vaucher) return;
  activeVoucher.value = pay.vaucher;
  voucherDialog.value = true;
};

const formatMoney = (value) => {
  const n = Number(value);
  if (!Number.isFinite(n)) return '0.00';
  return n.toFixed(2);
};

const getQuotas = () => {
  loading.value = true;
  quotaStore.getQuotaByMonth(route.params.month, { year: route.query.year, owner: route.query.owner })
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

const getTitleQuota = (quota) => {
  return quota.type !== 3
    ? 'Mensualidad: ' + quota.month_label
    : 'Cuota especial'
}

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
}

const formatDateTime = (date) => {
  if (!date) return '';
  return moment(date).format('DD/MM/YYYY');
}

const hasPaymentInfo = (quota) => {
  return getDisplayPay(quota) !== null || Number(quota.status) !== 1;
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
        <div v-if="quotas.length > 0" class="md:px-5">
          <h2 class="text-h6 text-weight-bold q-mb-md">{{ pageTitle }}</h2>

          <div class="">
            <div v-for="quota in quotas" :key="quota.id"
              class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 mb-4"
              style="position: relative; border: 1px solid lightgrey">
              <div class="px-4 pb-2 pt-2 md:pt-4">
                <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                  <div class="flex-1 pr-20">
                    <div class="text-lg font-bold text-gray-900">
                      {{ quota.departament?.owner?.name }}
                    </div>
                    <!-- <div v-if="isAdminRoute" class="pt-1 text-xs font-medium text-gray-500">
                      {{ quota.departament?.owner?.name }}
                    </div> -->
                  </div>
                </div>

                <div class="space-y-2 pt-3 pb-2">
                  <div class="row items-center">
                    <div class="flex items-center text-sm text-gray-700 col-6 col-md-3">
                      <q-icon name="eva-home-outline" size="20px" class="mr-1 text-gray-500" />
                      <span class="font-medium">Unidad: <span class="text-uppercase font-medium">{{ quota.departament?.number }}</span></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-700 md:pt-4 pt-2 col-6 col-md-3 ">
                      <q-icon name="eva-pricetags-outline" size="20px" class="mr-1 text-gray-500" />
                      <span class="font-medium">Tipo: {{ quota.departament.type_label}}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-700 col-6 col-md-3">
                      <q-icon name="eva-credit-card-outline" size="20px" class="mr-1 text-gray-500" />
                      <span class="font-medium">Mant. S/. {{ formatMoney(quota.maintenance_amount) }}</span>
                    </div>
                    <div class="flex items-center text-sm text-gray-700 col-6 col-md-3 mt-2 md:mt-0">
                      <q-icon name="eva-droplet-outline" size="20px" class="mr-1 text-gray-500" />
                      <span class="font-medium">
                        <template v-if="quota.type !== 1">Agua S/. {{ formatMoney(quota.water_amount) }}</template>
                        <template v-else>Agua —</template>
                      </span>
                    </div>
                    <div class="flex items-center text-sm text-gray-700 col-6 col-md-3 mt-2 md:mt-0">
                      <q-icon name="eva-calendar-outline" size="20px" class="mr-1 text-gray-500"  />
                      <span class="font-medium">Vence: {{ formatDate(quota.due_date) }}</span>
                    </div>
                    
                    <div v-if="quota.number" class="flex items-center text-sm text-gray-600 pl-1   md:pt-4 pt-2 col-6 col-md-3">
                      <span><strong>N° cuota: #{{ quota.number }}</strong></span>
                    </div>
                    
                  </div>

                </div>
              </div>

              <!-- Detalle del pago (estilo viewQuota / cliente) -->
              <div v-if="hasPaymentInfo(quota)" class="px-4 py-3 md:px-5"
                style="border-top: 1px solid rgba(211, 211, 211, 0.6); background: #fafafa;">
                <div class="text-subtitle2 text-weight-bold text-grey-8 q-mb-sm">Detalle del pago</div>

                <template v-if="getDisplayPay(quota)">
                  <div class="quota-pay-detail">
                    <div class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Estado del pago</span>
                      <span class="font-semibold" :class="'text-' + getDisplayPay(quota).status_color">
                        {{ getDisplayPay(quota).status_label }}
                      </span>
                    </div>

                    <div v-if="getDisplayPay(quota).amount" class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Monto pagado</span>
                      <span class="text-gray-900 font-semibold">
                        S/. {{ formatMoney(getDisplayPay(quota).amount) }}
                      </span>
                    </div>

                    <div v-if="quota.amount > 0" class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Monto de la cuota</span>
                      <span class="text-gray-900 font-semibold">S/. {{ formatMoney(quota.amount) }}</span>
                    </div>

                    <div v-if="getDisplayPay(quota).pay_date" class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Fecha de pago</span>
                      <span class="text-gray-900 font-semibold">
                        {{ formatDateTime(getDisplayPay(quota).pay_date) }}
                      </span>
                    </div>

                    <div v-if="getDisplayPay(quota).pay_method" class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Método de pago</span>
                      <span class="text-gray-900 font-semibold">
                        {{ getDisplayPay(quota).pay_method?.name || 'S/N' }}
                      </span>
                    </div>

                    <div v-if="getDisplayPay(quota).reference" class="quota-pay-detail__row">
                      <span class="text-gray-600 font-medium">Nro. de operación</span>
                      <span class="text-gray-900 font-semibold">#{{ getDisplayPay(quota).reference }}</span>
                    </div>

                    <div v-if="getDisplayPay(quota).vaucher" class="flex flex-center q-mt-sm cursor-pointer"
                      @click="openVoucher(getDisplayPay(quota), $event)">
                      <div class="text-center text-subtitle2 text-primary text-bold font-medium text__vaucher"
                        style="text-decoration: underline dotted;">
                        Ver voucher de pago
                      </div>
                      <span class="ml-2" v-html="iconsApp.voucher" />
                    </div>
                  </div>
                </template>

                <div v-else class="text-sm text-grey-7">
                  Aún no se ha registrado un comprobante de pago para esta cuota.
                </div>
              </div>

              <div v-if="canValidateQuota(quota)" class="px-4 py-3 bg-warning border-t flex justify-center">
                <q-btn unelevated rounded color="white" text-color="warning" label="Validar pago"
                  icon="eva-checkmark-circle-2-outline" @click="goToValidate(quota)" />
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <div v-html="appIcons.mensuality" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cuotas</h3>
          <p class="text-gray-600 text-center mb-6">No se encontraron cuotas para este período.</p>
        </div>
      </div>
    </div>

    <voucherModal v-if="activeVoucher" :vaucher="activeVoucher" :dialog="voucherDialog"
      @close-modal="voucherDialog = false" />
  </div>
</template>

<style scoped lang="scss">
.quota-pay-detail {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.quota-pay-detail__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(211, 211, 211, 0.45);

  &:last-child {
    border-bottom: none;
  }
}

.text__vaucher {
  transition: opacity 0.2s ease;

  &:hover {
    opacity: 0.85;
  }
}
</style>
