<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { Notify } from 'quasar'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const monthlyBillsStore = useMonthlyBillsStore()
const quotaStore = useQuotaStore()

const bill = ref(null)
const loading = ref(false)
const generating = ref(false)
const error = ref(null)

const billId = computed(() => route.params.id || route.query.id)

const monthLabel = (month) => {
  const map = {
    1: 'Enero',
    2: 'Febrero',
    3: 'Marzo',
    4: 'Abril',
    5: 'Mayo',
    6: 'Junio',
    7: 'Julio',
    8: 'Agosto',
    9: 'Septiembre',
    10: 'Octubre',
    11: 'Noviembre',
    12: 'Diciembre'
  }
  return map[month] || `Mes ${month}`
}

const getBillById = async (id) => {
  try {
    loading.value = true
    error.value = null
    const response = await monthlyBillsStore.getMonthlyBillById(id)
    bill.value = response.data
  } catch (err) {
    error.value = err || 'Error al cargar el presupuesto mensual'
  } finally {
    loading.value = false
  }
}

const reload = () => {
  if (billId.value) getBillById(billId.value)
}

const progressPercent = computed(() => {
  if (!bill.value?.total_maintenance_budget) return 0
  const total = Number(bill.value.total_maintenance_budget)
  const spent = Number(bill.value.total_expenses || 0)
  if (total === 0) return 0
  return Math.min(100, Math.round((spent / total) * 100))
})

const goToList = () => router.push('/admin/monthly_bills/list')
const goToEdit = () => router.push('/admin/monthly_bills/edit/' + billId.value)

const generateQuotas = async () => {
  generating.value = true
  try {
    const res = await quotaStore.generateMonthlyQuotas(bill.value.month, bill.value.year)
    if (res?.code !== 200) throw res
    Notify.create({ color: 'positive', message: 'Cuotas generadas correctamente', timeout: 2500 })
  } catch (err) {
    Notify.create({ color: 'negative', message: err?.error || err?.message || 'Error al generar cuotas', timeout: 3000 })
  } finally {
    generating.value = false
  }
}

onMounted(() => {
  if (billId.value) {
    getBillById(billId.value)
  } else {
    error.value = 'ID no proporcionado'
  }
})
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-0 md:px-6 px-3 h-full" style="overflow: auto;">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium">Cargando presupuesto...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <div class="flex gap-3">
          <q-btn color="primary" outline @click="reload">Reintentar</q-btn>
          <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
        </div>
      </div>

      <div v-else-if="bill" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full">
          <div class="row w-full mb-3 items-start">
            <div class="col-12 row pt-3">
              <div class="col-md-2 col-6 px-4">
                <q-btn color="positive" rounded unelevated :loading="generating" @click="generateQuotas">
                  Generar quotas
                </q-btn>
              </div>
              <div class="col-md-2 col-4 px-4">
                <q-btn color="primary" class="rounded-lg" outline @click="goToEdit">
                  <q-icon  name="eva-edit-2-outline" />
                </q-btn>
              </div>
            </div>
            <div class="flex flex-col items-start col-md-8 col-7 md:pl-5 pl-3">
              <div class="mb-3 pt-5">
                <div class="text-2xl font-bold text-gray-900">
                  Presupuesto: {{ monthLabel(bill.month) }} {{ bill.year }}
                </div>
                <div class="text-grey-7 mt-1">
                  Creado: {{ moment(bill.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
            </div>
            
          </div>

          <div class="w-full md:p-5 px-4 pt-5 pb-7" style="border-top: 1px solid lightgray;">
            <div class="space-y-4">
              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Presupuesto mantenimiento</span>
                <span class="text-gray-900 font-semibold">S/. {{ bill.total_maintenance_budget }}</span>
              </div>
              <div v-if="bill.total_expenses > 0" class="q-mt-sm q-mb-md">
                <div class="flex justify-between items-center q-mb-xs">
                  <span class="text-caption text-grey-7">Gastado: S/. {{ Number(bill.total_expenses).toFixed(2) }}</span>
                  <span class="text-caption text-grey-7">{{ progressPercent }}%</span>
                </div>
                <q-linear-progress :value="progressPercent / 100" color="primary" size="20px" style="border-radius: 4px;">
                  <div class="absolute-full flex flex-center text-white text-bold text-caption">
                    {{ progressPercent }}%
                  </div>
                </q-linear-progress>
              </div>

              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Monto total recibo de agua</span>
                <span class="text-gray-900 font-semibold">{{ bill.total_water_bill_amount ?? '-' }}</span>
              </div>

              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Consumo total de agua (m³)</span>
                <span class="text-gray-900 font-semibold">{{ bill.total_water_consumption_m3 ?? '-' }}</span>
              </div>

              <div class="flex justify-between items-center pb-2" style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Costo unitario por m³</span>
                <span class="text-gray-900 font-semibold">S/. {{ bill.water_price_per_m3 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Presupuesto no encontrado</h2>
        <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.dateFact {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}
</style>

