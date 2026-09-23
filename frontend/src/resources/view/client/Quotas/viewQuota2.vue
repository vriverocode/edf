<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import iconsApp from '@/assets/icons/index';
import moment from 'moment';
import voucherModal from '@/components/pay/voucherModal.vue';


const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()

const quotaData = ref(null)
const isLoading = ref(false)
const errorMessage = ref(null)
const showVoucherModal = ref(false)
const maintenanceBreakdown = ref(null)
const waterBreakdown = ref(null)
const consolidatedQuotas = ref([])
const payData = ref(null)
const expenses = ref([])
const responsibleUnits = ref([])
const unitQuotas = ref([])

const departmentTypeLabel = (type) => {
  const labels = { 1: 'DPT', 2: 'EST', 3: 'DPO', 4: 'LAV' }
  return labels[type] || 'UNI'
}

const money = (value) => Number(value || 0).toFixed(2)

const buildBreakdownItem = (quota, isCurrent = false) => ({
  id: quota.id,
  departament_id: quota.departament_id ?? quota.departament?.id,
  number: quota.departament?.number || quota.departament_number || '—',
  type: quota.departament?.type,
  typeLabel: departmentTypeLabel(quota.departament?.type),
  maintenance_amount: quota.maintenance_amount,
  water_amount: quota.water_amount,
  extra_amount: quota.extra_amount,
  amount: quota.amount,
  has_water_reading: !!quota.water_reading_id,
  water_reading_id: quota.water_reading_id,
  waterReading: quota.waterReading || null,
  isCurrent,
})

const allQuotasForBreakdown = computed(() => {
  if (!quotaData.value) return []
  const current = buildBreakdownItem(quotaData.value, true)
  const others = consolidatedQuotas.value.map(q => buildBreakdownItem(q, false))
  return [current, ...others]
})

const hasConsolidation = computed(() => consolidatedQuotas.value.length > 0)

const totalMaintenance = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.maintenance_amount || 0), 0)
)

const totalWater = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.water_amount || 0), 0)
)

const totalExtras = computed(() =>
  allQuotasForBreakdown.value.reduce((sum, q) => sum + (q.extra_amount || 0), 0)
)

const totalExpenses = computed(() =>
  expenses.value.reduce((sum, e) => sum + Number(e.amount || 0), 0)
)

const participationRate = computed(() =>
  parseFloat(quotaData.value?.maintenance_participation_percentage || 0) / 100
)

const expenseAmountByPercentTotal = computed(() =>
  expenses.value.reduce(
    (sum, e) => sum + Number(e.amount || 0) * participationRate.value,
    0
  )
)

const expenseAmountByPercent = (expense) =>
  (Number(expense || 0) * participationRate.value).toFixed(2)

const expensesByCategory = computed(() => {
  const groups = new Map()
  const pct = participationRate.value
  for (const expense of expenses.value) {
    const key = expense.service_category || 'Sin categoría'
    if (!groups.has(key)) {
      groups.set(key, {
        name: key,
        items: [],
        total: 0,
        totalByPercent: 0,
      })
    }
    const group = groups.get(key)
    group.items.push(expense)
    group.total += Number(expense.amount || 0)
    group.totalByPercent += Number(expense.amount || 0) * pct
  }
  return Array.from(groups.values())
})

const subOtherCharges = computed(() => expenseAmountByPercentTotal.value)

const subWater = computed(() => Number(quotaData.value?.water_amount || 0))

const subMaintenance = computed(() => Number(quotaData.value?.maintenance_amount || 0))

const subExtras = computed(() => Number(quotaData.value?.extra_amount || 0))

const grandTotal = computed(() => Number(quotaData.value?.amount || 0))

const predioLabel = computed(() => {
  const predio = quotaData.value?.predio
  if (Array.isArray(predio) && predio.length) {
    return predio.join(', ')
  }
  if (Array.isArray(responsibleUnits.value) && responsibleUnits.value.length) {
    return responsibleUnits.value.map(u => u.number).filter(Boolean).join(', ')
  }
  return quotaData.value?.departament?.number || '—'
})

const collectionRate = computed(() => {
  const rate = Number(quotaData.value?.receipt?.collection_rate ?? 0)
  return rate ? `${rate}%` : '—'
})

const waterReading = computed(() => quotaData.value?.waterReading || null)

const waterConsumption = computed(() => {
  if (quotaData.value?.receipt?.water_consumption != null) {
    return Number(quotaData.value.receipt.water_consumption)
  }
  const reading = waterReading.value
  if (!reading) return 0
  return Math.max(0, Number(reading.current_reading || 0) - Number(reading.previous_reading || 0))
})

const waterFactor = computed(() => {
  if (quotaData.value?.receipt?.water_price_per_m3 != null) {
    return Number(quotaData.value.receipt.water_price_per_m3)
  }
  return Number(waterReading.value?.m3_price || quotaData.value?.monthly_bill?.water_price_per_m3 || 0)
})

const hasWaterSection = computed(() =>
  !!waterReading.value || Number(quotaData.value?.water_amount || 0) > 0
)

const currentDepartamentId = computed(() =>
  Number(quotaData.value?.departament_id ?? quotaData.value?.departament?.id)
)

const isCurrentUnit = (unit) => Number(unit.id) === currentDepartamentId.value

const sortedUnitQuotas = computed(() => {
  const typeOrder = { 1: 0, 2: 1, 3: 2, 4: 3 }
  return [...unitQuotas.value].sort((a, b) => {
    const ta = typeOrder[a.type] ?? 9
    const tb = typeOrder[b.type] ?? 9
    if (ta !== tb) return ta - tb
    return String(a.number || '').localeCompare(String(b.number || ''), 'es', { numeric: true })
  })
})

const unitQuotasTotal = computed(() =>
  sortedUnitQuotas.value.reduce((sum, q) => sum + Number(q.amount || 0), 0)
)

const unitQuotaForMonth = (unit) => {
  const match = sortedUnitQuotas.value.find(q =>
    Number(q.departament_id) === Number(unit.id) ||
    q.number === unit.number
  )
  if (match) return match.id
  return allQuotasForBreakdown.value.find(q =>
    Number(q.departament_id) === Number(unit.id) ||
    q.number === unit.number
  )?.id || null
}

const goToUnitQuota = (unit) => {
  const quotaId = unitQuotaForMonth(unit)
  if (quotaId && Number(quotaId) !== Number(quotaData.value?.id)) {
    router.push({ name: 'viewQuota', params: { id: quotaId } })
  }
}

const loadQuota = (id) => {
  if (!id) {
    errorMessage.value = 'ID de cuota no proporcionado'
    return
  }
  maintenanceBreakdown.value = null
  waterBreakdown.value = null
  consolidatedQuotas.value = []
  payData.value = null
  expenses.value = []
  responsibleUnits.value = []
  unitQuotas.value = []
  quotaData.value = null
  fetchQuotaById(id).then(() => fetchQuotaBreakdownDetails(id))
}

const fetchQuotaById = async (id) => {
  try {
    isLoading.value = true
    errorMessage.value = null

    const response = await quotaStore.getQuotaById(id)
    quotaData.value = response.data
    consolidatedQuotas.value = response.data.consolidated_quotas || []
    payData.value = response.data.pay
    expenses.value = response.data.expenses || []
    responsibleUnits.value = response.data.responsible_units || []
    unitQuotas.value = response.data.unit_quotas || []

  } catch (err) {
    console.error('Error al obtener la cuota:', err)
    errorMessage.value = err || 'Error al cargar la cuota'
  } finally {
    isLoading.value = false
  }
}

const fetchQuotaBreakdownDetails = async (id) => {
  try {
    const [maintenanceResponse, waterResponse] = await Promise.allSettled([
      quotaStore.getClientMaintenanceDetailByQuotaId(id),
      quotaStore.getClientWaterDetailByQuotaId(id),
    ])
    if (maintenanceResponse.status === 'fulfilled') maintenanceBreakdown.value = maintenanceResponse.value?.data
    if (waterResponse.status === 'fulfilled') waterBreakdown.value = waterResponse.value?.data
  } catch (e) {
    // silencioso
  }
}

const downloadReceipt = async () => {
  const currentQuotaId = quotaData.value?.id
  if (!currentQuotaId) return
  const token = localStorage.getItem('access_token')
  try {
    const res = await fetch('/api/bill-invoices/client-download/' + currentQuotaId, {
      headers: { Authorization: 'Bearer ' + token }
    })
    if (!res.ok) return
    const blob = await res.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'recibo-cuota-' + currentQuotaId + '.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    console.error('Error al descargar recibo:', e)
  }
}

const goToHome = () => {
  router.push('/client/quotas/list')
}

const reloadQuota = () => {
  loadQuota(route.params.id || route.query.id)
}

watch(
  () => route.params.id || route.query.id,
  (id) => loadQuota(id),
)

loadQuota(route.params.id || route.query.id)
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-0 md:px-6 px-3 h-full" style="overflow: auto;">
      <!-- Loading State -->
      <div v-if="isLoading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium">Cargando cuota...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="errorMessage" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ errorMessage }}</p>
        <button @click="reloadQuota"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <!-- Success State -->
      <div v-else-if="quotaData" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full">
          <!-- Header -->
          <div class="row w-full mb-3 items-start">
            <div class="flex  items-center col-md-9 col-12 md:pl-5 pl-3 md:order-first">
              <div class="mb-4 pt-5">
                <div class="bg-primary rounded-xl p-3">
                  <div v-html="iconsApp.mensuality2" />
                </div>
              </div>
              <div class="px-2">
                <h1 class="text-2xl font-bold text-gray-900 md:mb-2">Cuota mes: {{ quotaData.month_label }}</h1>
                <div>
                  <q-chip :color="quotaData.status_color" text-color="white" class="text-weight-bold">
                    {{ quotaData.status_label }}
                  </q-chip>
                </div>
              </div>
            </div>
            <div class="col-md-3 col-12 text-right order-first ">
              <div class="flex justify-end md:pb-1">
                <div class="p-4 dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Creada el:</span>
                  {{ moment(quotaData.created_at).format('DD/MM/YYYY') }}
                </div>
              </div>
              
            </div>
            <div class="col-12 px-5">
              <div class="row">
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4">
                  <div class="text-grey-7 font-medium text-md">Predio:</div>
                  <div class="text-primary text-md font-bold" style="text-transform: uppercase;">
                    {{ predioLabel }}
                  </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4 flex flex-col items-end md:items-start">
                  <div class="text-grey-7 font-medium text-md">Propietario:</div>
                  <div class="text-primary text-md font-bold">{{ quotaData.departament?.owner?.name ?? '—' }}</div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4">
                  <div class="text-grey-7 font-medium text-md">Responsable de pago:</div>
                  <div class="text-primary text-md font-bold">
                    {{ quotaData.responsible_pivot?.user?.name ?? quotaData.departament?.owner?.name }}
                  </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4 flex flex-col items-end md:items-start">
                  <div class="text-grey-7 font-medium text-md">Total cuota mes:</div>
                  <div class="text-primary text-md font-bold">S/. {{ money(grandTotal) }}</div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4">
                  <div class="text-grey-7 font-medium text-md">Fecha de emisión:</div>
                  <div class="text-primary text-md font-bold">
                    {{ moment(quotaData.created_at).format('DD/MM/YYYY') }}
                  </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-6 col-md-4 flex flex-col items-end md:items-start">
                  <div class="text-grey-7 font-medium text-md">Vencimiento:</div>
                  <div class="text-primary text-md font-bold">
                    {{ quotaData.receipt?.due_date || (quotaData.due_date ? moment(quotaData.due_date).format('DD/MM/YYYY') : '—') }}
                  </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-4">
                  <div class="text-grey-7 font-medium text-md">% Participación:</div>
                  <div class="text-primary text-md font-bold">
                    {{ quotaData.maintenance_participation_percentage ?? '—' }}%
                  </div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-4 flex flex-col items-center md:items-start">
                  <div class="text-grey-7 font-medium text-md">% Cobro:</div>
                  <div class="text-primary text-md font-bold">
                    {{ parseFloat(quotaData.maintenance_participation_percentage).toFixed(2) ?? '—' }}%</div>
                </div>
                <div class="mt-4 md:mt-2 md:pr-5 pr-3 col-4 flex flex-col items-end md:items-start">
                  <div class="text-grey-7 font-medium text-md">Tipo de área:</div>
                  <div class="text-primary text-md font-bold">
                    {{ quotaData.departament?.type_label ?? departmentTypeLabel(quotaData.departament?.type) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Unidades a cargo -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Unidades a cargo</h3>
            <div
              class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100"
              v-for="unit in responsibleUnits"
              :key="'unit-' + unit.id"
            >
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  class="text-blue-600 hover:underline font-medium"
                  :class="{ 'font-bold': isCurrentUnit(unit) }"
                  @click="goToUnitQuota(unit)"
                >
                  {{ unit.number }}
                </button>
                <span class="text-xs text-gray-500">{{ departmentTypeLabel(unit.type) }}</span>
                <span
                  v-if="isCurrentUnit(unit)"
                  class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold"
                >
                  🟢
                </span>
              </div>
              <span v-if="unit.area != null" class="text-xs text-gray-500">{{ Number(unit.area).toFixed(2) }} m²</span>
            </div>
            <div
              v-if="!responsibleUnits.length"
              class="text-sm text-gray-500 py-1.5"
            >
              {{ predioLabel }}
            </div>
          </div>

          <!-- Gastos del mes (monthly bill) -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Gastos del mes</h3>
            <template v-if="expensesByCategory.length">
              <div
                v-for="group in expensesByCategory"
                :key="'cat-' + group.name"
                class="mb-4"
              >
                <div class="flex justify-between items-center py-1.5 mb-1 border-b border-gray-200">
                  <span class="text-sm font-bold text-primary uppercase">{{ group.name }}</span>
                  <span class="text-sm font-bold text-gray-900 whitespace-nowrap">S/. {{ money(group.totalByPercent) }}</span>
                </div>
                <div
                  v-for="expense in group.items"
                  :key="'exp-' + expense.id"
                  class="py-2 text-sm border-b border-gray-100"
                >
                  <div class="row">
                    <div class="row  col-12">
                      <div class="font-semibold text-gray-800 col-12">
                        {{ expense.description }}
                      </div>
                      <div class="text-gray-600 col-6">
                        {{ expense.provider || '' }}
                      </div>
                      <div class="text-gray-500 text-xs col-6">
                        <span v-if="expense.invoice_number">Fact. {{ expense.invoice_number }}</span>
                      </div>
                    </div>
                    <div class="col-12 pt-2 row">
                      <div class="col-4 ">
                        <span class="font-medium text-gray-800 whitespace-nowrap">S/. {{ money(expense.amount) }}</span>
                      </div>
                      <div class="col-4 flex justify-center">
                        {{ parseFloat(quotaData.maintenance_participation_percentage).toFixed(3) }}%
                      </div>
                      <div class="col-4 flex justify-end">
                        {{ expenseAmountByPercent(expense.amount) }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1"
              >
                <span class="text-gray-900">Sub-total Cargos del mes</span>
                <span class="text-primary">S/. {{ money(expenseAmountByPercentTotal) }}</span>
              </div>
            </template>
            <div v-else class="text-sm text-gray-500">
              No hay gastos registrados para este mes.
            </div>
          </div>

          <!-- Desglose de la cuota -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Desglose de cuotas del mes</h3>

            <!-- Unidades DPT / EST / DPO con monto de cuota -->
            <div v-if="sortedUnitQuotas.length" class="mb-5">
              <div
                v-for="uq in sortedUnitQuotas"
                :key="'uq-' + uq.id"
                class="flex justify-between items-center py-2 text-sm border-b border-gray-100"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span
                    class="text-[10px] font-semibold px-1.5 py-0.5 rounded"
                    :class="uq.type === 1 ? 'bg-blue-100 text-blue-700' : uq.type === 2 ? 'bg-amber-100 text-amber-700' : uq.type === 3 ? 'bg-gray-100 text-gray-700' : 'bg-purple-100 text-purple-700'"
                  >
                    {{ uq.type_short || departmentTypeLabel(uq.type) }}
                  </span>
                  <router-link
                    :to="{ name: 'viewQuota', params: { id: uq.id } }"
                    class="text-blue-600 hover:underline font-medium truncate"
                    :class="{ 'font-bold': uq.is_current }"
                  >
                    {{ uq.number }}
                  </router-link>
                  <span
                    v-if="uq.is_current"
                    class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold"
                  >
                    ESTA
                  </span>
                  <span
                    v-if="uq.status_label"
                    class="text-[10px] px-1.5 py-0.5 rounded border border-gray-200 text-gray-500"
                  >
                    {{ uq.status_label }}
                  </span>
                </div>
                <span
                  class="font-medium whitespace-nowrap"
                  :class="uq.is_current ? 'text-primary font-semibold' : 'text-gray-800'"
                >
                  S/. {{ money(uq.amount) }}
                </span>
              </div>
              <div
                class="flex justify-between items-center py-2 text-sm font-bold border-t border-gray-300 mt-1"
              >
                <span class="text-gray-900">Total cuotas unidades (DPT/EST/DPO)</span>
                <span class="text-primary">S/. {{ money(unitQuotasTotal) }}</span>
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 mb-4">
              No hay cuotas de unidades para este mes.
            </div>

            <!-- Mantenimiento (cuota actual + consolidadas) -->
            <div class="mb-4" v-if="hasConsolidation">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Mantenimiento</h4>
              <div v-if="maintenanceBreakdown" class="text-xs text-gray-400 mb-2">
                Presupuesto total: S/. {{ maintenanceBreakdown.maintenance_budget_total }}
              </div>
              <div v-for="q in allQuotasForBreakdown" :key="'mnt-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                    class="text-blue-600 hover:underline font-medium" :class="{ 'font-bold': q.isCurrent }">
                    {{ q.number }}
                  </router-link>
                  <span v-if="q.isCurrent"
                    class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold">
                    ESTA
                  </span>
                </div>
                <span class="font-medium text-gray-800">S/. {{ money(q.maintenance_amount) }}</span>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal mantenimiento</span>
                <span class="text-primary">S/. {{ money(totalMaintenance) }}</span>
              </div>
            </div>

            <!-- Consumo de agua -->
            <div v-if="hasConsolidation && allQuotasForBreakdown.some(q => q.has_water_reading)" class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Consumo de agua</h4>
              <div v-for="q in allQuotasForBreakdown.filter(q => q.has_water_reading)" :key="'water-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <div class="flex items-center gap-2">
                  <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                    class="text-blue-600 hover:underline font-medium" :class="{ 'font-bold': q.isCurrent }">
                    {{ q.number }}
                  </router-link>
                  <span v-if="q.waterReading" class="text-xs text-gray-500">
                    {{ q.waterReading.previous_reading }} → {{ q.waterReading.current_reading }} m³
                  </span>
                </div>
                <router-link :to="{ name: 'quotaWaterDetailClient', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium">
                  S/. {{ money(q.water_amount) }}
                </router-link>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal agua</span>
                <span class="text-primary">S/. {{ money(totalWater) }}</span>
              </div>
            </div>

            <!-- Cargos extras -->
            <div v-if="hasConsolidation && allQuotasForBreakdown.some(q => q.extra_amount > 0)" class="mb-4">
              <h4 class="text-sm font-bold text-gray-700 mb-2">Cargos extras</h4>
              <div v-for="q in allQuotasForBreakdown.filter(q => q.extra_amount > 0)" :key="'extra-' + q.id"
                class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <router-link :to="{ name: 'viewQuota', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium">
                  {{ q.number }}
                </router-link>
                <span class="font-medium text-orange-700">S/. {{ money(q.extra_amount) }}</span>
              </div>
              <div v-if="hasConsolidation"
                class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
                <span class="text-gray-900">Subtotal extras</span>
                <span class="text-orange-700">S/. {{ money(totalExtras) }}</span>
              </div>
            </div>
          </div>

          <!-- Lectura de agua (solo datos) -->
          <div v-if="hasWaterSection" class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Lectura de agua</h3>
            <div v-if="waterReading" class="text-xs text-gray-400 mb-2">
              Agua común mes:
              S/. {{ money(quotaData.monthly_bill?.common_water_cost) }}
              <span v-if="quotaData.monthly_bill?.common_water_consumption_m3 != null">
                ({{ quotaData.monthly_bill.common_water_consumption_m3 }} m³)
              </span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-gray-500 border-b border-gray-200">
                    <th class="py-2 pr-2 font-medium">Concepto</th>
                    <th class="py-2 pr-2 font-medium text-right">Lectura anterior (A)</th>
                    <th class="py-2 pr-2 font-medium text-right">Lectura actual (B)</th>
                    <th class="py-2 pr-2 font-medium text-right">Consumo m³</th>
                    <th class="py-2 pr-2 font-medium text-right">Factor</th>
                    <th class="py-2 font-medium text-right">Importe</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-b border-gray-100">
                    <td class="py-2 pr-2 text-gray-800">Consumo de agua (B−A)</td>
                    <td class="py-2 pr-2 text-right text-gray-800">
                      {{ waterReading ? Number(waterReading.previous_reading).toFixed(3) : '—' }}
                    </td>
                    <td class="py-2 pr-2 text-right text-gray-800">
                      {{ waterReading ? Number(waterReading.current_reading).toFixed(3) : '—' }}
                    </td>
                    <td class="py-2 pr-2 text-right text-gray-800">{{ Number(waterConsumption).toFixed(3) }}</td>
                    <td class="py-2 pr-2 text-right text-gray-800">{{ Number(waterFactor).toFixed(2) }}</td>
                    <td class="py-2 text-right font-medium text-gray-800">S/. {{ money(quotaData.water_amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Subtotales + total del mes -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Subtotales del mes</h3>
            <div class="space-y-2">
              <div class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <span class="text-gray-600 font-medium">Sub-total otros cargos del mes</span>
                <span class="text-gray-900 font-semibold">S/. {{ money(subOtherCharges) }}</span>
              </div>
              <div class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <span class="text-gray-600 font-medium">Sub-total agua</span>
                <span class="text-gray-900 font-semibold">S/. {{ money(subWater) }}</span>
              </div>
              <div class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <span class="text-gray-600 font-medium">Sub-total mantenimiento del mes</span>
                <span class="text-gray-900 font-semibold">S/. {{ money(subMaintenance) }}</span>
              </div>
              <div v-if="subExtras > 0" class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100">
                <span class="text-gray-600 font-medium">Sub-total cargos extras</span>
                <span class="text-gray-900 font-semibold">S/. {{ money(subExtras) }}</span>
              </div>
              <div class="flex justify-between items-center py-3 text-base font-bold border-t-2 border-gray-200">
                <span class="text-gray-900">TOTAL DEL MES</span>
                <span class="text-primary text-lg">S/. {{ money(grandTotal) }}</span>
              </div>
            </div>
          </div>

          <!-- Datos del pago -->
          <div v-if="payData" class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray;">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Datos del pago</h3>
            <div class="space-y-3">
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Estado</span>
                <span class="font-semibold" :class="'text-' + quotaData.status_color">{{ quotaData.status_label }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Fecha de pago</span>
                <span class="text-gray-900 font-semibold">
                  {{ payData.pay_date ? moment(payData.pay_date).format('DD/MM/YYYY') : '—' }}
                </span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Método de pago</span>
                <span class="text-gray-900 font-semibold">{{ payData.pay_method?.name ?? 'S/N' }}</span>
              </div>
              <div v-if="payData.credit_applied > 0" class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Saldo a favor aplicado</span>
                <span class="font-semibold text-green-600">S/. {{ Number(payData.credit_applied).toFixed(2) }}</span>
              </div>
              <div v-if="hasConsolidation" class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Cuotas consolidadas</span>
                <span class="text-gray-900 font-semibold">{{ allQuotasForBreakdown.length }} cuotas</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">N° de cuota</span>
                <span class="text-gray-900 font-semibold">#{{ quotaData.number }}</span>
              </div>
              <div class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                <span class="text-gray-600 font-medium">Nro. de operación</span>
                <span class="text-gray-900 font-semibold">#{{ payData.reference ?? '—' }}</span>
              </div>
            </div>
            <div class="flex flex-center mt-4" @click="showVoucherModal = true">
              <div class="text-center text-subtitle1 text-primary text-bold font-medium cursor-pointer text__vaucher"
                style="text-decoration:dotted">
                Voucher de pago
              </div>
              <span class="ml-2" v-html="iconsApp.voucher"></span>
            </div>
          </div>

          <!-- Botones de acción -->
          <div class="w-full space-y-4">
            <button @click="downloadReceipt" v-if="quotaData.status === 3"
              class="w-full py-4 border border-gray-300 rounded-xl font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors flex items-center justify-center space-x-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                </path>
              </svg>
              <span>Descargar Recibo</span>
            </button>
          </div>
        </div>
        <template v-if="payData">
          <voucherModal :vaucher="payData.vaucher" :dialog="showVoucherModal"
            @closeModal="showVoucherModal = false" />
        </template>
      </div>

      <!-- No Quota Found -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
            </path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Cuota no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La cuota solicitada no existe o no tienes permisos para verla.</p>
        <button @click="goToHome"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver al inicio
        </button>
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
