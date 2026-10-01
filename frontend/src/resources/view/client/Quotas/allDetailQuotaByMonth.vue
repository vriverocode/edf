<script setup>
import { ref, onMounted, computed } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { usePayStore } from '@/services/store/pay.store';
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
const payStore = usePayStore();
const voucherDialog = ref(false);
const activeVoucher = ref(null);
const monthlyBill = ref(null);
const monthExpenses = ref([]);
const totalExpenses = ref(0);
const totalParticipationPercent = ref(0);
const creditBalances = ref({});
const creditItems = ref([]);

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

const goToEdit = (quota) => {
  router.push(`/admin/quota/edit/${quota.id}`);
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

// `amount` (double en BD) puede diferir de la Σ de componentes redondeados por < 0.01;
// en ese caso se usa la Σ para que Mant.+Agua coincida con el monto mostrado.
const quotaTotal = (quota) => {
  const components =
    Number(quota.maintenance_amount || 0) +
    Number(quota.water_amount || 0) +
    Number(quota.extra_amount || 0);
  const amount = Number(quota.amount || 0);
  return Math.abs(components - amount) < 0.01 ? components : amount;
};

const expensesByCategory = computed(() => {
  const groups = new Map();
  for (const expense of monthExpenses.value) {
    const key = expense.service_category || 'Sin categoría';
    if (!groups.has(key)) {
      groups.set(key, { name: key, items: [], total: 0 });
    }
    const group = groups.get(key);
    group.items.push(expense);
    group.total += Number(expense.amount || 0);
  }
  return Array.from(groups.values());
});

const fetchCreditBalances = async () => {
  creditBalances.value = {};
  creditItems.value = [];
  const deptIds = [...new Set(
    quotas.value.map(q => q.departament?.id).filter(Boolean)
  )];
  if (!deptIds.length) return;
  try {
    const res = await payStore.getCreditBalanceForDepartments(
      deptIds,
      Number(route.params.month),
      route.query.year ? Number(route.query.year) : null
    );
    if (res?.code === 200 && res.data?.detail) {
      creditBalances.value = res.data.detail;
      creditItems.value = res.data.items || [];
    }
  } catch {}
};

const getQuotas = () => {
  loading.value = true;
  quotaStore.getQuotaByMonth(route.params.month, { year: route.query.year, owner: route.query.owner })
    .then((response) => {
      if (response.code !== 200) throw response;
      quotas.value = response.data;
      monthlyBill.value = response.monthly_bill || null;
      monthExpenses.value = response.expenses || [];
      totalExpenses.value = Number(response.total_expenses || 0);
      totalParticipationPercent.value = totalParticipation();
      fetchCreditBalances();
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
const totalParticipation = () => {
  let totalPart = 0;
  quotas.value.forEach(quota => {
    totalPart+=  parseFloat(quota.departament.participation_percentage)
  });
  return totalPart.toFixed(6);
}

const totalConsumo = () => {
  let totalPart = 0;
  quotas.value.forEach(quota => {
    totalPart+=  parseFloat(quota?.water_reading?.consumption || 0)
  });
  return totalPart.toFixed(4);
}

const totalUnits = () => {
  if (!quotas.value || !quotas.value.length) return '';
  const units = quotas.value
    .map(quota => quota.departament?.number || quota.departament_number)
    .filter(Boolean);
  return [...new Set(units)].join(' - ');
};

// Suma del agua de todas las cuotas del mes (proporcional al porcentaje de participación no aplica;
// water_amount ya viene calculado por unidad para cada propietario)
const totalWaterAmount = computed(() => {
  return quotas.value.reduce((sum, q) => sum + Number(q.water_amount || 0), 0);
});

// Saldo a favor total disponible para todas las unidades del mes
const totalCreditBalance = computed(() => {
  if (!creditItems.value.length) {
    // fallback: sumar por departament_id
    return quotas.value.reduce((sum, q) => {
      return sum + (creditBalances.value[q.departament?.id] || 0);
    }, 0);
  }
  const month = Number(route.params.month);
  const year = route.query.year ? Number(route.query.year) : null;
  const deptIds = quotas.value.map(q => q.departament?.id).filter(Boolean);
  return creditItems.value.reduce((sum, item) => {
    if (!deptIds.includes(item.departament_id)) return sum;
    if (item.balance <= 0) return sum;
    const m = Number(item.applicable_month || 0);
    if (m === 0) return sum; // sin mes fijado: no aplica automáticamente aquí
    const y = item.applicable_year;
    if (m === month && (y == null || Number(y) === year)) return sum + item.balance;
    return sum;
  }, 0);
});

// Mantenimiento proporcional (gastos del mes × % participación)
const totalMaintenanceProporcional = computed(() => {
  return Number(totalExpenses.value) * (Number(totalParticipationPercent.value) / 100);
});

// Gran total: mantenimiento + agua - saldo a favor (mínimo 0)
const grandTotal = computed(() => {
  return Math.max(0, totalMaintenanceProporcional.value + totalWaterAmount.value - totalCreditBalance.value);
});

const goToBill = (id) => {
  router.push('/admin/expenses/details/'+id)
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

          <!-- Gastos y presupuesto del mes -->
          <div
            v-if="monthlyBill || monthExpenses.length"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 mb-4"
            style="position: relative; border: 1px solid lightgrey"
          >
            <div class="px-4 pt-4 pb-2">
              <div class="text-lg font-bold text-gray-900">Gastos y presupuesto del mes</div>

              <div v-if="monthlyBill" class="row pt-3 pb-1">
                <div class="col-6 col-md-4 pt-2 md:pt-0">
                  <div class="text-xs text-gray-500">Presupuesto mantenimiento</div>
                  <div class="text-sm font-semibold text-gray-900">
                    S/. {{ formatMoney(monthlyBill.total_maintenance_budget) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-0">
                  <div class="text-xs text-gray-500">Monto total del agua </div>
                  <div class="text-sm font-semibold text-gray-900">
                    S/. {{ Number(monthlyBill.total_water_bill_amount || 0).toFixed(2) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-0">
                  <div class="text-xs text-gray-500">Total</div>
                  <div class="text-sm font-semibold text-gray-900">
                    S/. {{ formatMoney(monthlyBill.total_water_bill_amount + monthlyBill.total_maintenance_budget) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-3">
                  <div class="text-xs text-gray-500">Precio del agua</div>
                  <div class="text-sm font-semibold text-gray-900">
                   S/. {{ formatMoney(monthlyBill.water_price_per_m3) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-3">
                  <div class="text-xs text-gray-500">Total consumo de agua</div>
                  <div class="text-sm font-semibold text-gray-900">
                    {{ totalConsumo() }} m3
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-3">
                  <div class="text-xs text-gray-500">Total consumo de agua</div>
                  <div class="text-sm font-semibold text-gray-900">
                   S/. {{ (parseFloat(totalConsumo())*formatMoney(monthlyBill.water_price_per_m3)).toFixed(2) }} 
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-3">
                  <div class="text-xs text-gray-500">Total % Participación</div>
                  <div class="text-sm font-semibold text-gray-900">
                     {{totalParticipationPercent}}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2 md:pt-3">
                  <div class="text-xs text-gray-500">Unidades</div>
                  <div class="text-sm font-semibold text-gray-900">
                     {{ totalUnits() }}
                  </div>
                </div>
              </div>
            </div>

            <div v-if="expensesByCategory.length" class="px-4 pb-4 md:px-5">
              <div
                v-for="group in expensesByCategory"
                :key="'cat-' + group.name"
                class="mb-3"
              >
                <div class="flex justify-between items-center py-1.5 mb-1 border-b border-gray-200">
                  <span class="text-sm font-bold text-primary uppercase">{{ group.name }}</span>
                  <span class="text-sm font-semibold text-gray-800">S/. {{ (formatMoney(group.total)* (totalParticipationPercent/100)).toFixed(2) }}</span>
                </div>
                <div
                  v-for="expense in group.items"
                  :key="'exp-' + expense.id"
                  class="py-2 text-sm border-b border-gray-100"
                >
                  <div class="flex justify-between items-start gap-3">
                    <div class="flex-1">
                      <div class="font-semibold text-gray-800">{{ expense.description }}</div>
                      <div class="text-gray-600">{{ expense.provider || '' }}</div>
                      <div class="text-gray-600 text-xs text-bold">
                        <span class="text-bold"><i class="text-bold cursor-pointer" style="text-decoration: underline;" @click="goToBill(expense.id)">S/. {{formatMoney(expense.amount) }}</i> x {{totalParticipationPercent}}%</span>
                      </div>
                      <div class="text-gray-500 text-xs">
                        <span v-if="expense.invoice_number">Fact. {{ expense.invoice_number }}</span>
                      </div>
                    </div>
                    <span class="font-medium text-gray-800 whitespace-nowrap">
                      S/. {{ (formatMoney(expense.amount) * (totalParticipationPercent/100)).toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Total gastos del mes</span>
                <span class="text-primary">S/. {{ totalMaintenanceProporcional.toFixed(2) }}</span>
              </div>

              <!-- Agua -->
              <div v-if="totalWaterAmount > 0" class="flex justify-between items-center py-1 text-sm border-t border-gray-100">
                <span class="flex items-center gap-1 text-gray-700">
                  <q-icon name="eva-droplet-outline" size="16px" />
                  Agua (total cuotas)
                </span>
                <span class="font-semibold text-gray-900">+ S/. {{ totalWaterAmount.toFixed(2) }}</span>
              </div>

              <!-- Saldo a favor -->
              <div v-if="totalCreditBalance > 0" class="flex justify-between items-center py-1 text-sm border-t border-gray-100">
                <span class="flex items-center gap-1 text-green-700">
                  <q-icon name="eva-checkmark-circle-2-outline" size="16px" class="text-green-600" />
                  Saldo a favor
                </span>
                <span class="font-semibold text-green-700">- S/. {{ totalCreditBalance.toFixed(2) }}</span>
              </div>

              <!-- Gran total -->
              <div class="flex justify-between items-center py-2 text-sm font-bold border-t-2 border-gray-400 mt-1">
                <span class="text-gray-900">Total a pagar</span>
                <span class="text-primary text-base">S/. {{ grandTotal.toFixed(2) }}</span>
              </div>
            </div>
            <div v-else class="px-4 pb-4 text-sm text-gray-500">
              No hay gastos registrados para este mes.
            </div>
          </div>

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
                        <template v-if="quota.type = 1">Agua S/. {{ formatMoney(quota.water_amount) }}</template>
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
                      <span class="text-gray-900 font-semibold">S/. {{ formatMoney(quotaTotal(quota)) }}</span>
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

              <div v-if="isAdminRoute" class="px-4 py-3 bg-primary border-t flex justify-center gap-2">
                <q-btn icon="eva-edit-outline" rounded size="sm" color="white" text-color="primary"
                  @click="goToEdit(quota)" />
                <q-btn v-if="canValidateQuota(quota)" unelevated rounded color="white" text-color="warning"
                  label="Validar pago" icon="eva-checkmark-circle-2-outline" @click="goToValidate(quota)" />
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
