<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useAnnualBudgetStore } from '@/services/store/annualBudget.store'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const annualBudgetStore = useAnnualBudgetStore()

const loading = ref(false)
const error = ref(null)
const budget = ref(null)

const statusLabel = (status) => {
  const map = { 1: 'Borrador', 2: 'Activo', 3: 'Cerrado' }
  return map[status] || 'Desconocido'
}

const statusColor = (status) => {
  const map = { 1: 'grey', 2: 'positive', 3: 'negative' }
  return map[status] || 'grey'
}

const fetchBudget = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await annualBudgetStore.getAnnualBudget(route.params.id)
    if (response?.code !== 200) throw response
    budget.value = response.data
  } catch (err) {
    error.value = err?.error || err?.message || 'No se pudo cargar el presupuesto'
    Notify.create({ color: 'negative', message: error.value, timeout: 2000 })
  } finally {
    loading.value = false
  }
}

const goTo = (url) => router.push(url)

onMounted(fetchBudget)
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-0 md:px-36 px-3 pb-12 h-full" style="overflow: auto;">
      <!-- LOADING -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>
      <!-- ERROR -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="bg-red-1 rounded-full p-6 mb-4">
          <q-icon name="eva-alert-circle-outline" size="3rem" color="negative" />
        </div>
        <div class="text-lg font-semibold text-gray-900 mb-1">Error</div>
        <div class="text-gray-600 text-center mb-4">{{ error }}</div> 
      </div>
      <!-- CONTENT -->
      <div v-else-if="budget" class="bg-white rounded-xl shadow-lg border border-gray-100 w-full">
        <!-- Header -->
        <div class="flex justify-between items-center pb-2 px-6 pt-4" style="border-bottom: 1px solid lightgrey;">
          <div>
            <div class="text-h5 font-bold text-gray-900">{{ budget.name }}</div>
            <div class="text-caption text-grey-6">Año {{ budget.year }}</div>
          </div>
          <div class="flex items-center">
            <q-badge :color="statusColor(budget.status)" :label="statusLabel(budget.status)" class="q-mr-md" />
            <q-btn flat round dense icon="eva-edit-2-outline" color="primary" @click="goTo(`/admin/budget/annual/${budget.id}/edit`)">
              <q-tooltip>Editar</q-tooltip>
            </q-btn>
          </div>
        </div>
        <!-- Stats -->
        <div class="row px-6 pt-4">
          <div class="col-12 col-md-4 q-pa-xs">
            <div class="bg-blue-1 rounded-lg py-2 px-3 text-center">
              <div class="text-caption text-grey-6">Total mensual</div>
              <div class="text-h6 font-bold">S/. {{ Number(budget.total_monthly_budget || 0).toFixed(2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-4 q-pa-xs">
            <div class="bg-teal-1 rounded-lg py-2 px-3 text-center">
              <div class="text-caption text-grey-6">Total anual</div>
              <div class="text-h6 font-bold">S/. {{ (Number(budget.total_monthly_budget || 0) * 12).toFixed(2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-4 q-pa-xs">
            <div class="bg-orange-1 rounded-lg py-2 px-3 text-center">
              <div class="text-caption text-grey-6">Templates</div>
              <div class="text-h6 font-bold">{{ budget.templates?.length || 0 }}</div>
            </div>
          </div>
        </div>
        <!-- Templates Table -->
        <div class="px-6 pt-4 pb-6">
          <div class="text-subtitle1 font-bold text-gray-900 q-mb-sm">Gastos del presupuesto</div>
          <q-table
            flat
            bordered
            :rows="budget.templates || []"
            :columns="[
              { name: 'sort_order', label: '#', field: 'sort_order', align: 'center', style: 'width: 50px' },
              { name: 'description', label: 'Descripción', field: 'description' },
              { name: 'amount', label: 'Monto mensual', field: 'amount', format: v => `S/. ${Number(v || 0).toFixed(2)}`, align: 'right' },
              { name: 'expense_type', label: 'Tipo', field: 'expense_type', format: v => v === 1 ? 'Ordinario' : 'Extraordinario' },
            ]"
            row-key="id"
            hide-pagination
            hide-bottom
          />
          <div class="flex justify-end q-mt-sm">
            <div class="text-subtitle1 font-bold">
              Total mensual: S/. {{ Number(budget.total_template_amount || 0).toFixed(2) }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
