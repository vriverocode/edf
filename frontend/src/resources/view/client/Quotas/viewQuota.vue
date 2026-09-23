<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import { useAuthStore } from '@/services/store/auth.services'
import { storeToRefs } from 'pinia'
import moment from 'moment'
import voucherModal from '@/components/pay/voucherModal.vue'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()
const authStore = useAuthStore()
const { currencySymbol } = storeToRefs(authStore)

const amountPrefix = computed(() => currencySymbol.value || 'S/')

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

const getUnitInfo = (type) => {
  if (type === 2) return { label: 'Estacionamiento' }
  if (type === 3) return { label: 'Depósito' }
  if (type === 4) return { label: 'Lavandería' }
  return { label: 'Departamento' }
}

const money = (value) => Number(value || 0).toFixed(2)

const formatCurrency = (val) => Number(val || 0).toFixed(2)

const formatDate = (date) => {
  if (!date) return '---'
  return moment(date).format('DD [de] MMMM [de] YYYY')
}

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

const expenseAmountByPercent = (expense) => {
  return (Number(expense || 0) * participationRate.value).toFixed(2)
}

watch(
  () => route.params.id || route.query.id,
  (id) => loadQuota(id),
)

loadQuota(route.params.id || route.query.id)
</script>

<template>
  <div class="h-full invoice-page">
    <div class="h-full invoice-scroll">

      <!-- Loading -->
      <div v-if="isLoading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="5rem" />
      </div>

      <!-- Error -->
      <div v-else-if="errorMessage" class="flex flex-col items-center justify-center py-20 px-6">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-4">
          <q-icon name="eva-alert-triangle-outline" color="negative" size="2.5rem" />
        </div>
        <h2 class="text-lg font-bold text-gray-900 mb-2">Error</h2>
        <p class="text-gray-600 text-center mb-6">{{ errorMessage }}</p>
        <q-btn outline color="primary" label="Reintentar" no-caps @click="reloadQuota" />
      </div>

      <!-- Invoice -->
      <div v-else-if="quotaData" class="invoice-container">

        <!-- ═══ Header ═══ -->
        <div class="invoice-header">
          <div class="invoice-header__top">
            <div>
              <div class="invoice-header__label">Detalle de cuota</div>
              <div class="invoice-header__title">Cuota mes: {{ quotaData.month_label }}</div>
            </div>
          </div>
          <div class="invoice-header__id">{{ predioLabel }}</div>
          <q-chip
            :color="quotaData.status_color"
            text-color="white"
            :icon="quotaData.status_icon"
            :label="quotaData.status_label"
            dense
            class="invoice-header__badge"
          />
        </div>

        <!-- ═══ Info general ═══ -->
        <div class="invoice-section">
          <div class="invoice-info-grid">
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Predio</span>
              <span class="invoice-info-item__value">{{ predioLabel }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Propietario</span>
              <span class="invoice-info-item__value">{{ quotaData.owner_profile?.name ?? quotaData.departament?.owner?.name ?? '—' }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Responsable de pago</span>
              <span class="invoice-info-item__value">{{ quotaData.responsible_pivot?.user?.name ?? quotaData.departament?.owner?.name ?? '—' }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Total cuota mes</span>
              <span class="invoice-info-item__value">{{ amountPrefix }} {{ money(grandTotal) }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Fecha de emisión</span>
              <span class="invoice-info-item__value">{{ moment(quotaData.created_at).format('DD/MM/YYYY') }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Vencimiento</span>
              <span class="invoice-info-item__value">
                {{ quotaData.receipt?.due_date || (quotaData.due_date ? moment(quotaData.due_date).format('DD/MM/YYYY') : '—') }}
              </span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">% Participación</span>
              <span class="invoice-info-item__value">{{ quotaData.maintenance_participation_percentage ?? '—' }}%</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Tipo de área</span>
              <span class="invoice-info-item__value">{{ quotaData.departament?.type_label ?? departmentTypeLabel(quotaData.departament?.type) }}</span>
            </div>
          </div>
        </div>

        <!-- ═══ Unidades a cargo ═══ -->
        <div class="invoice-section">
          <div class="invoice-section__title">Unidades a cargo</div>
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
                ESTA
              </span>
            </div>
            <span v-if="unit.area != null" class="text-xs text-gray-500">{{ Number(unit.area).toFixed(2) }} m²</span>
          </div>
          <div v-if="!responsibleUnits.length" class="text-sm text-gray-500 py-1.5">
            {{ predioLabel }}
          </div>
        </div>

        <!-- ═══ Gastos del mes ═══ -->
        <div class="invoice-section">
          <div class="invoice-section__title">Gastos del mes</div>
          <template v-if="expensesByCategory.length">
            <div
              v-for="group in expensesByCategory"
              :key="'cat-' + group.name"
              class="mb-4"
            >
              <div class="flex justify-between items-center py-1.5 mb-1 border-b border-gray-200">
                <span class="text-sm font-bold text-primary uppercase">{{ group.name }}</span>
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
                    <div class="text-gray-500 text-xs">
                      <span v-if="expense.invoice_number">Fact. {{ expense.invoice_number }}</span>
                    </div>
                  </div>
                  <span class="font-medium text-gray-800 whitespace-nowrap">
                    {{ amountPrefix }} {{ expenseAmountByPercent(expense.amount) }}
                  </span>
                </div>
              </div>
            </div>
            <div class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
              <span class="text-gray-900">Sub-total Cargos del mes</span>
              <span class="text-primary">{{ amountPrefix }} {{ money(expenseAmountByPercentTotal) }}</span>
            </div>
          </template>
          <div v-else class="text-sm text-gray-500">
            No hay gastos registrados para este mes.
          </div>
        </div>

        <!-- ═══ Desglose de cuotas del mes ═══ -->
        <div class="invoice-section">
          <div class="invoice-section__title">Desglose de cuotas del mes</div>

          <!-- Unidades DPT / EST / DPO -->
          <div v-if="sortedUnitQuotas.length" class="invoice-table mb-4">
            <div class="invoice-table__header">
              <div class="invoice-table__col invoice-table__col--unit">Unidad</div>
              <div class="invoice-table__col invoice-table__col--amount">Mant.</div>
              <div class="invoice-table__col invoice-table__col--amount">Agua</div>
              <div class="invoice-table__col invoice-table__col--total">Subtotal</div>
            </div>
            <div v-for="uq in sortedUnitQuotas" :key="'uq-' + uq.id" class="invoice-table__row">
              <div class="invoice-table__col invoice-table__col--unit">
                <div>
                  <div class="invoice-table__unit-label">
                    {{ getUnitInfo(uq.type).label }}
                    <span v-if="uq.is_current" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold ml-1">ESTA</span>
                  </div>
                  <div class="invoice-table__unit-number">
                    <router-link
                      :to="{ name: 'viewQuota', params: { id: uq.id } }"
                      class="text-blue-600 hover:underline"
                      :class="{ 'font-bold': uq.is_current }"
                    >
                      {{ uq.number }}
                    </router-link>
                    <span
                      v-if="uq.status_label"
                      class="text-[10px] px-1.5 py-0.5 rounded border border-gray-200 text-gray-500 ml-1"
                    >
                      {{ uq.status_label }}
                    </span>
                  </div>
                </div>
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ amountPrefix }} {{ formatCurrency(uq.maintenance_amount) }}
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                <template v-if="uq.water_amount > 0">
                  {{ amountPrefix }} {{ formatCurrency(uq.water_amount) }}
                </template>
                <span v-else class="text-gray-400">---</span>
              </div>
              <div class="invoice-table__col invoice-table__col--total">
                {{ amountPrefix }} {{ formatCurrency(uq.amount) }}
              </div>
            </div>
          </div>
          <div v-else class="text-sm text-gray-500 mb-4">
            No hay cuotas de unidades para este mes.
          </div>

          <!-- Mantenimiento consolidado -->
          <div class="mb-4" v-if="hasConsolidation">
            <div class="invoice-section__title" style="margin-bottom: 0.5rem;">Mantenimiento</div>
            <div v-if="maintenanceBreakdown" class="text-xs text-gray-400 mb-2">
              Presupuesto total: {{ amountPrefix }} {{ maintenanceBreakdown.maintenance_budget_total }}
            </div>
            <div
              v-for="q in allQuotasForBreakdown"
              :key="'mnt-' + q.id"
              class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100"
            >
              <div class="flex items-center gap-2">
                <router-link
                  :to="{ name: 'viewQuota', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium"
                  :class="{ 'font-bold': q.isCurrent }"
                >
                  {{ q.number }}
                </router-link>
                <span v-if="q.isCurrent" class="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-semibold">ESTA</span>
              </div>
              <span class="font-medium text-gray-800">{{ amountPrefix }} {{ money(q.maintenance_amount) }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
              <span class="text-gray-900">Subtotal mantenimiento</span>
              <span class="text-primary">{{ amountPrefix }} {{ money(totalMaintenance) }}</span>
            </div>
          </div>

          <!-- Consumo de agua consolidado -->
          <div v-if="hasConsolidation && allQuotasForBreakdown.some(q => q.has_water_reading)" class="mb-4">
            <div class="invoice-section__title" style="margin-bottom: 0.5rem;">Consumo de agua</div>
            <div
              v-for="q in allQuotasForBreakdown.filter(q => q.has_water_reading)"
              :key="'water-' + q.id"
              class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100"
            >
              <div class="flex items-center gap-2">
                <router-link
                  :to="{ name: 'viewQuota', params: { id: q.id } }"
                  class="text-blue-600 hover:underline font-medium"
                  :class="{ 'font-bold': q.isCurrent }"
                >
                  {{ q.number }}
                </router-link>
                <span v-if="q.waterReading" class="text-xs text-gray-500">
                  {{ q.waterReading.previous_reading }} → {{ q.waterReading.current_reading }} m³
                </span>
              </div>
              <router-link
                :to="{ name: 'quotaWaterDetailClient', params: { id: q.id } }"
                class="text-blue-600 hover:underline font-medium"
              >
                {{ amountPrefix }} {{ money(q.water_amount) }}
              </router-link>
            </div>
            <div class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
              <span class="text-gray-900">Subtotal agua</span>
              <span class="text-primary">{{ amountPrefix }} {{ money(totalWater) }}</span>
            </div>
          </div>

          <!-- Cargos extras consolidados -->
          <div v-if="hasConsolidation && allQuotasForBreakdown.some(q => q.extra_amount > 0)" class="mb-4">
            <div class="invoice-section__title" style="margin-bottom: 0.5rem;">Cargos extras</div>
            <div
              v-for="q in allQuotasForBreakdown.filter(q => q.extra_amount > 0)"
              :key="'extra-' + q.id"
              class="flex justify-between items-center py-1.5 text-sm border-b border-gray-100"
            >
              <router-link
                :to="{ name: 'viewQuota', params: { id: q.id } }"
                class="text-blue-600 hover:underline font-medium"
              >
                {{ q.number }}
              </router-link>
              <span class="font-medium text-orange-700">{{ amountPrefix }} {{ money(q.extra_amount) }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 text-sm font-bold border-t border-gray-300 mt-1">
              <span class="text-gray-900">Subtotal extras</span>
              <span class="text-orange-700">{{ amountPrefix }} {{ money(totalExtras) }}</span>
            </div>
          </div>
        </div>

        <!-- ═══ Lectura de agua ═══ -->
        <div v-if="hasWaterSection" class="invoice-section">
          <div class="invoice-section__title">Lectura de agua</div>
          <div v-if="waterReading" class="text-xs text-gray-400 mb-2">
            Agua común mes: {{ amountPrefix }} {{ money(quotaData.monthly_bill?.common_water_cost) }}
            <span v-if="quotaData.monthly_bill?.common_water_consumption_m3 != null">
              ({{ quotaData.monthly_bill.common_water_consumption_m3 }} m³)
            </span>
          </div>
          <div class="invoice-table">
            <div class="invoice-table__header">
              <div class="invoice-table__col invoice-table__col--unit">Concepto</div>
              <div class="invoice-table__col invoice-table__col--amount">Ant. (A)</div>
              <div class="invoice-table__col invoice-table__col--amount">Act. (B)</div>
              <div class="invoice-table__col invoice-table__col--amount">Consumo</div>
              <div class="invoice-table__col invoice-table__col--amount">Factor</div>
              <div class="invoice-table__col invoice-table__col--total">Importe</div>
            </div>
            <div class="invoice-table__row">
              <div class="invoice-table__col invoice-table__col--unit">
                <div>
                  <div class="invoice-table__unit-label">Consumo de agua (B−A)</div>
                </div>
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ waterReading ? Number(waterReading.previous_reading).toFixed(3) : '—' }}
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ waterReading ? Number(waterReading.current_reading).toFixed(3) : '—' }}
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ Number(waterConsumption).toFixed(3) }}
              </div>
              <div class="invoice-table__col invoice-table__col--amount">
                {{ Number(waterFactor).toFixed(2) }}
              </div>
              <div class="invoice-table__col invoice-table__col--total">
                {{ amountPrefix }} {{ money(quotaData.water_amount) }}
              </div>
            </div>
          </div>
        </div>

        <!-- ═══ Totales ═══ -->
        <div class="invoice-section invoice-totals">
          <div class="invoice-totals__row">
            <span>Sub-total otros cargos del mes</span>
            <span>{{ amountPrefix }} {{ money(subOtherCharges) }}</span>
          </div>
          <div class="invoice-totals__row">
            <span>Sub-total agua</span>
            <span>{{ amountPrefix }} {{ money(subWater) }}</span>
          </div>
          <div class="invoice-totals__row">
            <span>Sub-total mantenimiento del mes</span>
            <span>{{ amountPrefix }} {{ money(subMaintenance) }}</span>
          </div>
          <div v-if="subExtras > 0" class="invoice-totals__row">
            <span>Sub-total cargos extras</span>
            <span>{{ amountPrefix }} {{ money(subExtras) }}</span>
          </div>
          <div class="invoice-totals__row invoice-totals__row--grand">
            <span>TOTAL DEL MES</span>
            <span>{{ amountPrefix }} {{ money(grandTotal) }}</span>
          </div>
        </div>

        <!-- ═══ Datos del pago ═══ -->
        <div v-if="payData" class="invoice-section">
          <div class="invoice-section__title">Datos del pago</div>
          <div class="invoice-info-grid">
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Estado</span>
              <span class="invoice-info-item__value" :class="'text-' + quotaData.status_color">{{ quotaData.status_label }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Fecha de pago</span>
              <span class="invoice-info-item__value">
                {{ payData.pay_date ? moment(payData.pay_date).format('DD/MM/YYYY') : '—' }}
              </span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Método de pago</span>
              <span class="invoice-info-item__value">{{ payData.pay_method?.name ?? 'S/N' }}</span>
            </div>
            <div v-if="payData.credit_applied > 0" class="invoice-info-item">
              <span class="invoice-info-item__label">Saldo a favor aplicado</span>
              <span class="invoice-info-item__value text-green-600">{{ amountPrefix }} {{ Number(payData.credit_applied).toFixed(2) }}</span>
            </div>
            <div v-if="hasConsolidation" class="invoice-info-item">
              <span class="invoice-info-item__label">Cuotas consolidadas</span>
              <span class="invoice-info-item__value">{{ allQuotasForBreakdown.length }} cuotas</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">N° de cuota</span>
              <span class="invoice-info-item__value">#{{ quotaData.number }}</span>
            </div>
            <div class="invoice-info-item">
              <span class="invoice-info-item__label">Nro. de operación</span>
              <span class="invoice-info-item__value">#{{ payData.reference ?? '—' }}</span>
            </div>
          </div>
          <div class="flex flex-center justify-center mt-4 cursor-pointer" @click="showVoucherModal = true">
            <span class="text-subtitle1 text-primary text-bold" style="text-decoration:dotted">Voucher de pago</span>
          </div>
        </div>

        <!-- ═══ Actions ═══ -->
        <div class="invoice-section invoice-actions">
          <q-btn
            v-if="quotaData.status === 3"
            outline
            color="primary"
            icon="eva-download-outline"
            label="Descargar Recibo"
            no-caps
            class="invoice-actions__btn"
            @click="downloadReceipt"
          />
        </div>
      </div>

      <!-- Not found -->
      <div v-else class="flex flex-col items-center justify-center py-20 px-6">
        <q-icon name="eva-file-remove-outline" size="4rem" color="grey-5" class="mb-4" />
        <h2 class="text-lg font-bold text-gray-900 mb-2">Cuota no encontrada</h2>
        <p class="text-gray-600 text-center mb-6">La cuota solicitada no existe o no tienes permisos para verla.</p>
        <q-btn outline color="grey-7" label="Volver al inicio" no-caps @click="goToHome" />
      </div>
    </div>

    <!-- Voucher modal -->
    <template v-if="payData?.vaucher">
      <voucherModal :vaucher="payData.vaucher" :dialog="showVoucherModal" @closeModal="showVoucherModal = false" />
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
  text-transform: uppercase;
}

/* ── Sections ── */
.invoice-section {
  background: #fff;
  padding: 0.8rem 0.8rem;
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
</style>
