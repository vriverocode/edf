<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useExpenseStore } from '@/services/store/expense.store'
import { useProviderStore } from '@/services/store/provider.store'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'
import moment from 'moment'

const expenseStore = useExpenseStore()
const providerStore = useProviderStore()
const categoryStore = useServiceCategoryStore()

const now = new Date()
const loading = ref(false)
const expenses = ref([])
const filter = ref({ month: null, year: now.getFullYear(), status: null, provider_id: null, category_id: null })
const providers = ref([])
const categories = ref([])

const monthOptions = [
  { value: null, name: 'Todos' },
  { value: 1, name: 'Enero' }, { value: 2, name: 'Febrero' }, { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' }, { value: 5, name: 'Mayo' }, { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' }, { value: 8, name: 'Agosto' }, { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' }, { value: 11, name: 'Noviembre' }, { value: 12, name: 'Diciembre' }
]

const totalAmount = computed(() => expenses.value.reduce((s, e) => s + (Number(e.amount) || 0), 0))

const totalByCategory = computed(() => {
  const map = {}
  expenses.value.forEach(e => {
    const cat = e.service_category_name || 'Sin categoría'
    map[cat] = (map[cat] || 0) + (Number(e.amount) || 0)
  })
  return Object.entries(map).sort((a, b) => b[1] - a[1])
})

const fetchExpenses = async () => {
  loading.value = true
  try {
    const params = { ...filter.value, per_page: 999 }
    const response = await expenseStore.getExpenses(params)
    if (response?.code === 200) {
      expenses.value = response.data?.data || []
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: 'Error al cargar gastos' })
  } finally {
    loading.value = false
  }
}

const loadFormOptions = async () => {
  try {
    const res = await expenseStore.getExpenseFormOptions()
    if (res?.code === 200) {
      providers.value = res.data?.providers || []
      categories.value = res.data?.service_categories || []
    }
  } catch (e) { /* ignore */ }
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

onMounted(() => { loadFormOptions(); fetchExpenses() })
</script>
<template>
  <div class="md:px-20 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">Reporte de gastos</div>
    <div class="row q-mb-md q-col-gutter-xs">
      <div class="col-6 col-md-3">
        <q-select v-model="filter.month" :options="monthOptions" option-label="name" option-value="value"
          emit-value map-options dense borderless class="form__inputsR" label="Mes" clearable
          @update:model-value="fetchExpenses" />
      </div>
      <div class="col-6 col-md-3">
        <q-input v-model.number="filter.year" type="number" dense borderless class="form__inputsR" label="Año"
          @update:model-value="fetchExpenses" />
      </div>
      <div class="col-6 col-md-3">
        <q-select v-model="filter.provider_id" :options="providers" option-label="name" option-value="id"
          emit-value map-options dense borderless class="form__inputsR" label="Proveedor" clearable
          @update:model-value="fetchExpenses" />
      </div>
      <div class="col-6 col-md-3">
        <q-select v-model="filter.category_id" :options="categories" option-label="name" option-value="id"
          emit-value map-options dense borderless class="form__inputsR" label="Categoría" clearable
          @update:model-value="fetchExpenses" />
      </div>
    </div>
    <div v-if="loading" class="flex justify-center py-10"><q-spinner-dots color="primary" size="3rem" /></div>
    <div v-else>
      <div class="row q-mb-md">
        <div class="col-12 col-md-4 q-pa-xs">
          <div class="bg-primary text-white q-pa-md rounded-borders">
            <div class="text-caption">Total gastos</div>
            <div class="text-h5 text-bold">{{ formatMoney(totalAmount) }}</div>
            <div class="text-caption">{{ expenses.length }} registros</div>
          </div>
        </div>
        <div class="col-12 col-md-8 q-pa-xs">
          <div class="bg-grey-2 q-pa-md rounded-borders">
            <div class="text-subtitle2 text-black q-mb-sm">Por categoría</div>
            <div v-for="[cat, amt] in totalByCategory" :key="cat" class="row items-center q-py-xs">
              <div class="col text-body2">{{ cat }}</div>
              <div class="col-auto text-weight-bold">{{ formatMoney(amt) }}</div>
            </div>
          </div>
        </div>
      </div>
      <q-table flat bordered :rows="expenses" :columns="[
        { name: 'provider_name', label: 'Proveedor', field: 'provider_name', align: 'left' },
        { name: 'description', label: 'Descripción', field: 'description', align: 'left' },
        { name: 'amount', label: 'Monto', field: 'amount', format: v => formatMoney(v), align: 'right' },
        { name: 'expense_date', label: 'Fecha', field: 'expense_date', format: v => v ? moment(v).format('DD/MM/YYYY') : '—', align: 'center' },
        { name: 'status_name', label: 'Estado', field: 'status_name', align: 'center' },
      ]" row-key="id" hide-pagination virtual-scroll :rows-per-page-options="[50, 100, 999]" />
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
