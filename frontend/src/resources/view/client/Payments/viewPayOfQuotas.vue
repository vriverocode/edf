<script setup>
import { ref, onMounted, computed } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useAuthStore } from '@/services/store/auth.services';
import { storeToRefs } from 'pinia';
import { useRoute, useRouter } from 'vue-router';
import moment from 'moment';
import voucherModal from '@/components/pay/voucherModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const pay = ref(null);
const loading = ref(true);
const error = ref(null);
const dialog = ref(false);
const quotaStore = useQuotaStore();
const authStore = useAuthStore();
const { currencySymbol } = storeToRefs(authStore);
const router = useRouter();
const route = useRoute();

const amountPrefix = computed(() => currencySymbol.value || 'S/');

const getUnitInfo = (type) => {
  if (type === 2) return { label: 'Estacionamiento'};
  if (type === 3) return { label: 'Depósito'};
  return { label: 'Departamento'};
}

const getData = () => {
  loading.value = true;
  error.value = null;
  quotaStore.getQuotaByPayId(route.params.id)
    .then((response) => {
      if (response.code !== 200) throw response;
      pay.value = response.data;
    })
    .catch((err) => {
      console.error(err);
      error.value = 'No se pudo cargar el detalle del pago';
    })
    .finally(() => {
      loading.value = false;
    });
}

const quotas = computed(() => pay.value?.quotas || []);
const totalMaintenance = computed(() => quotas.value.reduce((s, q) => s + Number(q.maintenance_amount || 0), 0));
const totalWater = computed(() => quotas.value.reduce((s, q) => s + Number(q.water_amount || 0), 0));
const totalAmount = computed(() => Number(pay.value?.amount || 0));
const monthLabel = computed(() => quotas.value.length > 0 ? quotas.value[0].month_label : '---');
const yearLabel = computed(() => {
  if (!quotas.value.length || !quotas.value[0].due_date) return '';
  return new Date(quotas.value[0].due_date).getFullYear();
});

const formatDate = (date) => {
  if (!date) return '---';
  return moment(date).format('DD [de] MMMM [de] YYYY');
}

const formatCurrency = (val) => {
  return Number(val || 0).toFixed(2);
}
onMounted(() => {
  getData();
});
</script>

<template>
  <div class="h-full invoice-page">
    <div class="h-full invoice-scroll">

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="5rem" />
      </div>

      <!-- Error -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-20 px-6">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
          <q-icon name="eva-alert-triangle-outline" color="negative" size="2.5rem" />
        </div>
        <h2 class="text-lg font-bold text-gray-900 mb-2">Error</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <q-btn outline color="primary" label="Reintentar" no-caps @click="getData" />
      </div>

      <!-- Invoice -->
      <div v-else-if="pay" class="invoice-container">

        <!-- ═══ Header ═══ -->
        <div class="invoice-header">
          <div class="invoice-header__top">
            <div>
              <div class="invoice-header__label">Resumen de pago</div>
              <div class="invoice-header__title">Cuotas {{ monthLabel }} {{ yearLabel }}</div>
            </div>
          </div>
          <div class="invoice-header__id ">{{ pay.user.name }}</div>
          <!-- <div class="invoice-header__id pt-2">#{{ pay.pay_id }}</div> -->
          <q-chip
              :color="pay.status_color"
              text-color="white"
              :icon="pay.status_icon"
              :label="pay.status_label"
              dense
              class="invoice-header__badge"
            />
        </div>

        <!-- ═══ Info general ═══ -->
        <div class="invoice-section">
          <div class="invoice-info-grid">
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Fecha de pago</span>
              <span class="invoice-info-item__value">{{ formatDate(pay.pay_date) }}</span>
            </div>
            <div class="invoice-info-item" v-if="pay.pay_method_obj || pay.pay_method">
              <span class="invoice-info-item__label">Método</span>
              <span class="invoice-info-item__value">{{ pay.pay_method_obj?.name || pay.pay_method?.name || '---' }}</span>
            </div>
            <div class="invoice-info-item" v-if="pay.reference && pay.reference !== '000000'">
              <span class="invoice-info-item__label">Referencia</span>
              <span class="invoice-info-item__value">#{{ pay.reference }}</span>
            </div>
            <div class="invoice-info-item" v-if="pay.user">
              <span class="invoice-info-item__label">Propietario</span>
              <span class="invoice-info-item__value">{{ pay.user.name }}</span>
            </div>
          </div>
        </div>

        <!-- ═══ Detalle de cuotas ═══ -->
        <div class="invoice-section">
          <div class="invoice-section__title">Detalle por unidad</div>

          <div class="invoice-table">
            <!-- Header -->
            <div class="invoice-table__header">
              <div class="invoice-table__col invoice-table__col--unit">Unidad</div>
              <div class="invoice-table__col invoice-table__col--amount">Mant.</div>
              <div class="invoice-table__col invoice-table__col--amount">Agua</div>
              <div class="invoice-table__col invoice-table__col--total">Subtotal</div>
            </div>

            <!-- Rows -->
            <div
              v-for="quota in quotas"
              :key="quota.id"
              class="invoice-table__row"
            >
              <div class="invoice-table__col invoice-table__col--unit">
                <div>
                  <div class="invoice-table__unit-label">{{ getUnitInfo(quota.departament?.type).label }}</div>
                  <div class="invoice-table__unit-number">{{ quota.departament?.number }}</div>
                </div>
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ amountPrefix }} {{ formatCurrency(quota.maintenance_amount) }}
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                <template v-if="quota.water_amount > 0">
                  {{ amountPrefix }} {{ formatCurrency(quota.water_amount) }}
                </template>
                <span v-else class="text-gray-400">---</span>
              </div>
              <div class="invoice-table__col invoice-table__col--total">
                {{ amountPrefix }} {{ formatCurrency(quota.amount) }}
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ Totales ═══ -->
        <div class="invoice-section invoice-totals">
          <div class="invoice-totals__row">
            <span>Total Mantenimiento</span>
            <span>{{ amountPrefix }} {{ formatCurrency(totalMaintenance) }}</span>
          </div>
          <div class="invoice-totals__row">
            <span>Total Agua</span>
            <span>{{ amountPrefix }} {{ formatCurrency(totalWater) }}</span>
          </div>
          <div class="invoice-totals__row invoice-totals__row--grand">
            <span>Total Pagado</span>
            <span>{{ amountPrefix }} {{ formatCurrency(totalAmount) }}</span>
          </div>
        </div>

        <!-- ═══ Voucher ═══ -->
        <div class="invoice-section invoice-actions" v-if="pay.vaucher">
          <q-btn
            outline
            color="primary"
            icon="eva-file-text-outline"
            label="Ver comprobante"
            no-caps
            class="invoice-actions__btn"
            @click="dialog = true"
          />
        </div>
      </div>

      <!-- Not found -->
      <div v-else class="flex flex-col items-center justify-center py-20 px-6">
        <q-icon name="eva-file-remove-outline" size="4rem" color="grey-5" class="mb-4" />
        <h2 class="text-lg font-bold text-gray-900 mb-2">Pago no encontrado</h2>
        <p class="text-gray-600 text-center mb-6">No se encontró el pago solicitado.</p>
        <q-btn outline color="grey-7" label="Volver" no-caps @click="goBack" />
      </div>
    </div>

    <!-- Voucher modal -->
    <template v-if="pay?.vaucher">
      <voucherModal :vaucher="pay.vaucher" :dialog="dialog" @closeModal="dialog = false" />
    </template>
  </div>
</template>

<style scoped>
.invoice-page {
  overflow: hidden;
  background: #ffffffff;
}
.invoice-scroll {
  height: 100%;
  overflow: auto;
}

/* ── Container ── */
.invoice-container {
  max-width: 540px;
  margin: 0 auto;
  padding: 1.25rem 1rem 2rem;
}
@media (min-width: 768px) {
  .invoice-container {
    padding: 2rem 1.5rem 3rem;
  }
}

/* ── Header ── */
.invoice-header {
  background: linear-gradient(135deg, #1763a6 0%, #1a4f82 100%);
  border-radius: 1rem;
  padding: 1.25rem 1.25rem 1rem;
  color: #fff;
  margin-bottom: 0.75rem;
  position: relative;
}
.invoice-header__top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.75rem;
}
.invoice-header__label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.7;
  margin-bottom: 0.15rem;
}
.invoice-header__title {
  font-size: 1.35rem;
  font-weight: 700;
}
.invoice-header__badge {
  position: absolute;
  top: 0.4rem;
  right: 0.5rem;
  padding: 0.8rem;
}
.invoice-header__id {
  margin-top: 0.1rem;
  font-size: 0.8rem;
  opacity: 0.6;
  font-family: monospace;
}

/* ── Sections ── */
.invoice-section {
  background: #fff;
  /* border-radius: 0.875rem; */
  padding: 1rem 0.8rem;
  margin-bottom: 0.625rem;
}
.invoice-section__title {
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #6b7280;
  margin-bottom: 0.75rem;
}

/* ── Info grid ── */
.invoice-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.75rem 1rem;
}
.invoice-info-item {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.invoice-info-item__label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #9ca3af;
  font-weight: 500;
}
.invoice-info-item__value {
  font-size: 0.9rem;
  font-weight: 600;
  color: #1f2937;
}

/* ── Table ── */
.invoice-table {
  border: 1px solid #e5e7eb;
  border-radius: 0.625rem;
  overflow: hidden;
}
.invoice-table__header {
  display: flex;
  align-items: center;
  background: #f9fafb;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #e5e7eb;
}
.invoice-table__header .invoice-table__col {
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}
.invoice-table__row {
  display: flex;
  align-items: center;
  padding: 0.625rem 0.75rem;
  border-bottom: 1px solid #f3f4f6;
  transition: background 0.15s;
}
.invoice-table__row:last-child {
  border-bottom: none;
}
.invoice-table__row:hover {
  background: #fafbfd;
}
.invoice-table__col {
  font-size: 0.82rem;
  color: #374151;
}
.invoice-table__col--unit {
  flex: 1.6;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.invoice-table__col--amount {
  flex: 1;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.invoice-table__col--total {
  flex: 1.1;
  text-align: right;
  font-weight: 700;
  color: #111827;
  font-variant-numeric: tabular-nums;
}
.invoice-table__unit-icon {
  font-size: 1.15rem;
  flex-shrink: 0;
}
.invoice-table__unit-label {
  font-size: 0.72rem;
  color: #6b7280;
  line-height: 1.1;
}
.invoice-table__unit-number {
  font-size: 0.85rem;
  font-weight: 600;
  color: #1f2937;
}

/* ── Totals ── */
.invoice-totals {
  padding: 0.875rem 1.125rem;
}
.invoice-totals__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.85rem;
  color: #4b5563;
  padding: 0.3rem 0;
}
.invoice-totals__row--grand {
  margin-top: 0.5rem;
  padding-top: 0.65rem;
  border-top: 2px solid #e5e7eb;
  font-size: 1.1rem;
  font-weight: 700;
  color: #1763a6;
}

/* ── Actions ── */
.invoice-actions {
  display: flex;
  justify-content: center;
  background: transparent;
  border: none;
  padding: 0;
}
.invoice-actions__btn {
  width: 100%;
  border-radius: 0.75rem;
}

/* ── Back ── */
.invoice-back {
  display: flex;
  justify-content: center;
  padding: 0.5rem 0 1rem;
}
</style>
