<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useExpenseStore } from '@/services/store/expense.store'
import moment from 'moment'

const monthlyBillsStore = useMonthlyBillsStore()
const expenseStore = useExpenseStore()

const now = new Date()
const selectedYear = ref(now.getFullYear())
const availableYears = ref([now.getFullYear() - 1, now.getFullYear(), now.getFullYear() + 1])
const loading = ref(false)
const monthlyData = ref([])
const expenseTotal = ref(0)

const annualBudgetTotal = computed(() => {
  return monthlyData.value.reduce((sum, m) => sum + (Number(m.total_maintenance_budget) || 0), 0)
})

const annualWaterTotal = computed(() => {
  return monthlyData.value.reduce((sum, m) => sum + (Number(m.total_water_bill_amount) || 0), 0)
})

const annualExpenseTotal = computed(() => expenseTotal.value)

const fetchData = async () => {
  loading.value = true
  try {
    const response = await monthlyBillsStore.getMonthlyBills({ years: [selectedYear.value], per_page: 12 })
    if (response?.code === 200) {
      monthlyData.value = response.data?.data || []
    }
    const expResponse = await expenseStore.getExpenses({ year: selectedYear.value, per_page: 100 })
    if (expResponse?.code === 200) {
      const expenses = expResponse.data?.data || []
      expenseTotal.value = expenses.reduce((sum, e) => sum + (Number(e.amount) || 0), 0)
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: 'Error al cargar datos anuales' })
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

const monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
</script>
<template>
  <div class="md:px-20 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Presupuesto anual {{ selectedYear }}
    </div>
    <div class="flex justify-center q-mb-md">
      <q-select v-model="selectedYear" :options="availableYears" dense borderless class="form__inputsR"
        style="min-width: 120px;" @update:model-value="fetchData" />
    </div>
    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>
    <div v-else>
      <div class="row q-mb-md">
        <div class="col-12 col-md-4 q-pa-xs">
          <div class="bg-primary text-white q-pa-md rounded-borders">
            <div class="text-caption">Presupuesto mantenimiento</div>
            <div class="text-h5 text-bold">S/. {{ annualBudgetTotal.toFixed(2) }}</div>
          </div>
        </div>
        <div class="col-12 col-md-4 q-pa-xs">
          <div class="bg-teal text-white q-pa-md rounded-borders">
            <div class="text-caption">Presupuesto agua</div>
            <div class="text-h5 text-bold">S/. {{ annualWaterTotal.toFixed(2) }}</div>
          </div>
        </div>
        <div class="col-12 col-md-4 q-pa-xs">
          <div class="bg-orange text-white q-pa-md rounded-borders">
            <div class="text-caption">Gastos registrados</div>
            <div class="text-h5 text-bold">S/. {{ annualExpenseTotal.toFixed(2) }}</div>
          </div>
        </div>
      </div>
      <q-table flat bordered :rows="monthlyData" :columns="[
        { name: 'month', label: 'Mes', field: 'month', format: v => monthNames[v] || v },
        { name: 'total_maintenance_budget', label: 'Presupuesto mant.', field: 'total_maintenance_budget', format: v => `S/. ${Number(v || 0).toFixed(2)}` },
        { name: 'total_water_bill_amount', label: 'Monto agua', field: 'total_water_bill_amount', format: v => `S/. ${Number(v || 0).toFixed(2)}` },
        { name: 'total_water_consumption_m3', label: 'Consumo agua (m³)', field: 'total_water_consumption_m3', format: v => v || '—' },
      ]" row-key="id" hide-pagination virtual-scroll />
    </div>
  </div>
</template>
<style scoped>
.rounded-borders { border-radius: 0.5rem; }
.form__inputsR .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>
