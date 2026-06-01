<script setup>
import { ref, onMounted } from 'vue';
import { useQuotaStore } from '@/services/store/quota.store';
import { useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
});

const months = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();
const router = useRouter();

const getMonths = () => {
  loading.value = true;
  quotaStore
    .getAdminMonthlySummary()
    .then((response) => {
      if (response.code !== 200) throw response;
      months.value = response.data;
    })
    .catch((err) => {
      console.log(err);
    })
    .finally(() => {
      loading.value = false;
    });
};

const goToMonth = (row) => {
  router.push({
    name: 'quotasMaintenanceMonthDetail',
    params: { year: String(row.year), month: String(row.month) },
  });
};

const formatMoney = (value) => `S/. ${Number(value ?? 0).toFixed(2)}`;

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
};

onMounted(() => {
  getMonths();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 pt-2 md:px-28 pb-12 ">
        <div v-if="months.length > 0" class=" md:px-5 row">
          <div
            v-for="row in months"
            :key="`${row.year}-${row.month}`"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5 mb-4 cursor-pointer col-md-4 col-12  "
            style="position: relative; "
            @click="goToMonth(row)"
          >
            <div class="px-4 pb-3 pt-5 card__month " style="">
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <h3 class="text-lg font-bold text-gray-900 m-0">
                  Mensualidad: {{ row.month_label }} {{ row.year }}
                </h3>
                <q-badge
                  v-if="row.has_pending_validation"
                  color="warning"
                  class="text-white px-3 py-1 badge__float"
                >
                  {{ row.pending_validation_count }} pago(s) por validar
                </q-badge>
              </div>

              <div class="row  pt-2">
                <div class="col-6 col-md-4 pt-2">
                  <div class="text-caption text-grey-7">Total del mes</div>
                  <div class="text-subtitle1 text-weight-bold text-primary">
                    {{ formatMoney(row.total_amount) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2">
                  <div class="text-caption text-grey-7">Pagado</div>
                  <div class="text-subtitle1 text-weight-bold text-positive">
                    {{ formatMoney(row.total_paid) }}
                  </div>
                </div>
                <div class="col-6 col-md-4 pt-2">
                  <div class="text-caption text-grey-7">Pendiente</div>
                  <div class="text-subtitle1 text-weight-bold text-warning">
                    {{ formatMoney(row.total_pending) }}
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 text-sm text-gray-600 pt-3">
                <span>{{ row.units_count }} unidad(es)</span> -
                <span>{{ row.owners_count }} propietario(s)</span>
                <span v-if="row.due_date">Fecha límite: {{ formatDate(row.due_date) }}</span>
              </div>
            </div>

            <div class="px-4 py-3 md:py-3 bg-primary border-t">
              <div class="flex justify-center items-center text-white text-sm font-medium">
                <q-icon name="eva-arrow-forward-outline" color="white" size="1.25rem" class="q-mr-xs" />
                Ver cuotas del mes
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <div v-html="appIcons.mensuality" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cuotas registradas</h3>
          <p class="text-gray-600 text-center mb-6">Aún no se ha emitido ninguna cuota mensual.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.badge__float{
  position: absolute;
  top: 0rem;
  right: 0;
}
.card__month{
  border-top: 1px solid lightgrey;
  border-left: 1px solid lightgrey;
  border-right: 1px solid lightgrey;
  border-top-left-radius: 0.75rem;
  border-top-right-radius: 0.75rem;
}
</style>
