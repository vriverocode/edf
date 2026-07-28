<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useQuotaStore } from '@/services/store/quota.store'
import moment from 'moment'

const quotaStore = useQuotaStore()

const now = new Date()
const loading = ref(false)
const selectedMonth = ref(now.getMonth() + 1)
const selectedYear = ref(now.getFullYear())
const delinquents = ref([])

const monthOptions = [
  { value: 1, name: 'Enero' }, { value: 2, name: 'Febrero' }, { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' }, { value: 5, name: 'Mayo' }, { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' }, { value: 8, name: 'Agosto' }, { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' }, { value: 11, name: 'Noviembre' }, { value: 12, name: 'Diciembre' }
]

const totalDelinquentAmount = computed(() => {
  return delinquents.value.reduce((sum, d) => {
    return sum + (Number(d.total_unpaid) || 0)
  }, 0)
})

const fetchDelinquents = async () => {
  loading.value = true
  try {
    const response = await quotaStore.getAdminGroupedByOwnerForMonth(selectedMonth.value, {
      year: selectedYear.value
    })
    if (response?.code === 200) {
      const data = response.data || []
      delinquents.value = data
        .filter(d => {
          const unpaid = Number(d.total_unpaid) || 0
          return unpaid > 0
        })
        .sort((a, b) => (Number(b.total_unpaid) || 0) - (Number(a.total_unpaid) || 0))
    }
  } catch (e) {
    delinquents.value = []
    Notify.create({ color: 'negative', message: 'Error al cargar morosos' })
  } finally {
    loading.value = false
  }
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

onMounted(fetchDelinquents)
</script>
<template>
  <div class="md:px-36 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2 pb-4">Reporte de morosos</div>
    <div class="row q-mb-md justify-start q-col-gutter-sm">
      <div class="col-6 col-md-3">
        <q-select v-model="selectedMonth" :options="monthOptions" option-label="name" option-value="value"
          emit-value map-options dense borderless class="form__inputsRss" @update:model-value="fetchDelinquents" />
      </div>
      <div class="col-6 col-md-3">
        <q-input v-model.number="selectedYear" type="number" dense borderless class="form__inputsRss"
          @update:model-value="fetchDelinquents" />
      </div>
    </div>
    <div v-if="loading" class="flex justify-center py-10"><q-spinner-dots color="primary" size="3rem" /></div>
    <div v-else>
      <div class="bg-negative text-white q-pa-md rounded-borders q-mb-md" v-if="delinquents.length > 0">
        <div class="text-caption">Total pendiente de cobro</div>
        <div class="text-h4 text-bold">{{ formatMoney(totalDelinquentAmount) }}</div>
        <div class="text-caption">{{ delinquents.length }} propietarios con deuda</div>
      </div>
      <div v-if="delinquents.length === 0 && !loading" class="text-center text-grey-6 q-py-xl">
        <q-icon name="eva-checkmark-circle-2" size="4rem" color="positive" />
        <div class="text-h6 q-mt-sm">¡No hay morosos este período!</div>
      </div>
      <q-table v-else flat bordered :rows="delinquents" :columns="[
        { name: 'owner_name', label: 'Propietario', field: 'owner_name', align: 'left' },
        { name: 'total_amount', label: 'Total cuota', field: 'total_amount', format: v => formatMoney(v), align: 'right' },
        { name: 'total_paid', label: 'Pagado', field: 'total_paid', format: v => formatMoney(v), align: 'right' },
        { name: 'total_unpaid', label: 'Pendiente', field: 'total_unpaid', format: v => formatMoney(v), align: 'right' },
      ]" row-key="owner_id" hide-pagination virtual-scroll />
    </div>
  </div>
</template>
<style >
.rounded-borders { border-radius: 0.5rem; }
.form__inputsRss .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>
