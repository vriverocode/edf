<script setup>
import { computed, ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useRouter } from 'vue-router'
import includeExpensesModal from '@/components/monthlyBills/includeExpensesModal.vue'
import waterReadingsModal from '@/components/monthlyBills/waterReadingsModal.vue'


const loading = ref(false)
const monthlyBillsStore = useMonthlyBillsStore()
const now = new Date()
const router = useRouter();
const showExpensesModal = ref(false)
const selectedExpenseIds = ref([])
const selectedExpensesData = ref([])
const previousSelectedTotal = ref(0)
const showWaterReadingsModal = ref(false)
const waterReadingsData = ref([])
const totalWaterConsumption = ref(0)
const loadingConsumption = ref(false)
const monthOptions = [
  { value: 1, name: 'Enero' },
  { value: 2, name: 'Febrero' },
  { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' },
  { value: 5, name: 'Mayo' },
  { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' },
  { value: 8, name: 'Agosto' },
  { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' },
  { value: 11, name: 'Noviembre' },
  { value: 12, name: 'Diciembre' }
]

const formData = ref({
  month: monthOptions[now.getMonth() - 1],
  year: now.getFullYear(),
  monthly_budget: '',
  total_maintenance_budget: '',
  total_water_bill_amount: '',
  total_water_consumption_m3: null,
  common_water_consumption_m3: null,
  water_price_per_m3: ''
})
const parseMaskedMoney = (value) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(2))
}

const formatMaskedMoney = (value, decimals = 2) => {
  if (value === null || value === undefined) return ''
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return ''
  const fixed = n.toFixed(decimals)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const previousBill = ref(null)
const loadingPrev = ref(false)

const previousMonth = computed(() => {
  const m = formData.value.month?.value - 1
  const y = m === 0 ? formData.value.year - 1 : formData.value.year
  return { month: m === 0 ? 12 : m, year: y }
})

const loadPreviousMonthData = async () => {
  if (!formData.value.month?.value) return
  loadingPrev.value = true
  try {
    const response = await monthlyBillsStore.getMonthlyBills({
      month: previousMonth.value.month,
      year: previousMonth.value.year,
      per_page: 1
    })
    if (response?.code === 200) {
      const list = response.data?.data || []
      previousBill.value = list.length > 0 ? list[0] : null
    }
  } catch (e) {
    previousBill.value = null
  } finally {
    loadingPrev.value = false
  }
}

const loadWaterConsumption = async () => {
  if (!formData.value.month?.value || !formData.value.year) return
  loadingConsumption.value = true
  try {
    const response = await monthlyBillsStore.getWaterConsumptionByMonth(
      formData.value.month.value,
      formData.value.year
    )
    if (response?.code === 200) {
      waterReadingsData.value = response.data?.readings || []
      totalWaterConsumption.value = response.data?.total_consumption || 0
      formData.value.total_water_consumption_m3 = totalWaterConsumption.value

      const commonReading = waterReadingsData.value.find(r => r.is_common)
      if (commonReading) {
        formData.value.common_water_consumption_m3 = commonReading.consumption
      }
    }
  } catch (e) {
    waterReadingsData.value = []
    totalWaterConsumption.value = 0
  } finally {
    loadingConsumption.value = false
  }
}

const includedExpensesTotal = computed(() => previousSelectedTotal.value)

const formattedIncludedExpenses = computed(() => formatMaskedMoney(includedExpensesTotal.value))

const commonWaterCost = computed(() => {
  const commonConsumption = Number(formData.value.common_water_consumption_m3) || 0
  const price = parseMaskedMoney(formData.value.water_price_per_m3) || 0
  return Math.round(commonConsumption * price * 100) / 100
})

const formattedCommonWaterCost = computed(() => formatMaskedMoney(commonWaterCost.value))

const calculatedTotal = computed(() => {
  const budget = parseMaskedMoney(formData.value.monthly_budget) || 0
  const expenses = includedExpensesTotal.value
  const commonWater = commonWaterCost.value
  return budget + expenses + commonWater
})

const formattedCalculatedTotal = computed(() => formatMaskedMoney(calculatedTotal.value))

watch(calculatedTotal, (val) => {
  formData.value.total_maintenance_budget = formatMaskedMoney(val)
})

watch(() => formData.value.month, () => {
  loadPreviousMonthData()
  loadWaterConsumption()
})

watch(() => formData.value.year, () => {
  loadWaterConsumption()
})




const hasWaterTotals = computed(() => {
  const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
  const consumption = Number(formData.value.total_water_consumption_m3)
  const commonConsumption = Number(formData.value.common_water_consumption_m3) || 0
  const totalConsumption = consumption + commonConsumption
  return amount !== null && amount > 0 && Number.isFinite(totalConsumption) && totalConsumption > 0
})

const waterPriceReadonly = computed(() => hasWaterTotals.value)

watch(
  () => [formData.value.total_water_bill_amount, formData.value.total_water_consumption_m3, formData.value.common_water_consumption_m3],
  () => {
    if (!hasWaterTotals.value) return
    const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const consumption = Number(formData.value.total_water_consumption_m3) || 0
    const commonConsumption = Number(formData.value.common_water_consumption_m3) || 0
    const totalConsumption = consumption + commonConsumption
    if (amount === null || totalConsumption <= 0) return
    const computedPrice = amount / totalConsumption

    formData.value.water_price_per_m3 = Number.isFinite(computedPrice) ? formatMaskedMoney(computedPrice, 4) : ''
  }
)

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const onExpensesSelected = ({ totalAmount, expenseIds, expenses }) => {
  selectedExpenseIds.value = expenseIds
  selectedExpensesData.value = expenses
  previousSelectedTotal.value = totalAmount
}

const submit = async () => {
  loading.value = true
  try {
    const monthlyBudget = parseMaskedMoney(formData.value.monthly_budget)
    const totalMaintenanceBudget = parseMaskedMoney(formData.value.total_maintenance_budget)
    const totalWaterBillAmount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const waterPricePerM3 = parseMaskedMoney(formData.value.water_price_per_m3)

    const rawConsumption = formData.value.total_water_consumption_m3
    const waterConsumption = (rawConsumption === null || rawConsumption === '' || rawConsumption === 0) ? null : Number(rawConsumption)
    if (previousBill.value?.total_water_consumption_m3 && waterConsumption) {
      const prev = Number(previousBill.value.total_water_consumption_m3)
      if (prev > 0) {
        const diff = Math.abs(waterConsumption - prev) / prev
        if (diff > 0.5) {
          const proceed = window.confirm(
            `El consumo actual (${waterConsumption} m³) difiere en más del 50% del período anterior (${prev} m³). ¿Deseas continuar?`
          )
          if (!proceed) return
        }
      }
    }

    const payload = {
      month: formData.value.month?.value,
      year: Number(formData.value.year),
      monthly_budget: monthlyBudget,
      total_maintenance_budget: totalMaintenanceBudget,
      total_water_bill_amount: totalWaterBillAmount,
      total_water_consumption_m3: waterConsumption,
      common_water_consumption_m3: formData.value.common_water_consumption_m3 ? Number(formData.value.common_water_consumption_m3) : null,
      water_price_per_m3: waterPricePerM3
    }

    if (selectedExpenseIds.value.length > 0) {
      payload.expense_ids = selectedExpenseIds.value
    }

    const response = await monthlyBillsStore.createMonthlyBill(payload)
    if (response?.code !== 200) throw response

    showNotify('positive', 'Presupuesto mensual registrado con éxito')
    setTimeout(() => router.go(-1), 1000)
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo registrar el presupuesto mensual'
    showNotify('negative', apiError)
  } finally {
    loading.value = false
  }
}
</script>
<template>
  <div class="md:px-20 px-2  pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold  my-2">
      Presupuesto mensual
    </div>

    <q-form @submit="submit()">
      <div class="row w-full">
        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select dense borderless class="form__inputsR mt-1" v-model="formData.month" :options="monthOptions"
            option-label="name" option-value="value" :rules="[val => !!val || 'El mes es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number"
            v-model.number="formData.year" :rules="[val => !!val || 'El año es requerido']" />
        </div>

        <!-- 1. Presupuesto mensual base (EDITABLE) -->
        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Presupuesto mensual base (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.monthly_budget" mask="###.###.###,##" reverse-fill-mask inputmode="decimal"
            :rules="[
              val => parseMaskedMoney(val) !== null || 'El presupuesto base es requerido'
            ]" />
        </div>

        <!-- 2. Gastos incluidos (READONLY) -->
        <div v-if="selectedExpensesData.length > 0" class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Gastos incluidos (S/.)</div>
          <q-input dense borderless class="form__inputsR mt-1" color="grey-4" readonly
            v-model="formattedIncludedExpenses" />
        </div>

        <!-- 3. Total a distribuir (READONLY, RESALTADO) -->
        <div class="col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black text-weight-bold">Total a distribuir (S/.)</div>
          <q-input dense borderless class="form__inputsR mt-1" color="primary" readonly
            v-model="formattedCalculatedTotal" />
        </div>

        <div class="w-full flex justify-end">
          <q-btn
              flat
              color="primary"
              label="Incluir gastos"
              no-caps
              icon="eva-list-outline"
              class="q-mr-sm"
              @click="showExpensesModal = true"
            />
        </div>

        <div v-if="selectedExpensesData.length > 0" class="col-12 px-2 md:px-12 mt-2">
          <div class="expenses-summary q-pa-sm">
            <div class="text-caption text-grey-7 q-mb-xs">Detalle de gastos incluidos:</div>
            <div
              v-for="expense in selectedExpensesData"
              :key="expense.id"
              class="row items-center q-py-xs"
            >
              <div class="col text-body2 text-black">
                {{ expense.provider_name }}
              </div>
              <div class="col-auto text-body2 text-weight-bold" style="color: #18181b;">
                S/ {{ formatMaskedMoney(expense.amount) }}
              </div>
            </div>
            <q-separator class="q-my-xs" />
            <div class="row items-center">
              <div class="col text-subtitle2 text-black">Total gastos:</div>
              <div class="col-auto text-subtitle2 text-weight-bold" style="color: #18181b;">
                S/ {{ formatMaskedMoney(previousSelectedTotal) }}
              </div>
            </div>
          </div>
        </div>
        <div v-if="previousBill" class="col-12 mt-2 px-2 md:px-12">
          <q-banner class="bg-grey-2 rounded-borders">
            <template v-slot:avatar>
              <q-icon name="eva-info-outline" color="grey-7" />
            </template>
            <div class="text-caption text-grey-7">
              Consumo período anterior: <strong>{{ previousBill.total_water_consumption_m3 || '—' }} m³</strong>
              ({{ monthOptions[previousBill.month - 1]?.name }} {{ previousBill.year }})
            </div>
          </q-banner>
        </div>
        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto total recibo de agua (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_water_bill_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo total de agua (m³)</div>
          <div class="row items-center">
            <div class="col">
              <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number" step="0.001"
                v-model.number="formData.total_water_consumption_m3" :loading="loadingConsumption" />
            </div>
            <div class="col-auto" v-if="waterReadingsData.length > 0">
              <q-btn flat round dense icon="eva-list-outline" color="primary" @click="showWaterReadingsModal = true">
                <q-tooltip>Ver detalle de lecturas</q-tooltip>
              </q-btn>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo áreas comunes (m³)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number" step="0.001"
            v-model.number="formData.common_water_consumption_m3" />
        </div>

        <div v-if="commonWaterCost > 0" class="col-12 mt-1 px-2 md:px-12">
          <q-banner class="bg-blue-1 rounded-borders">
            <div class="text-caption text-blue-8">
              Costo agua áreas comunes: <strong>S/ {{ formattedCommonWaterCost }}</strong> ({{ formData.common_water_consumption_m3 }} m³ × S/ {{ formData.water_price_per_m3 }})
              — se suma al total a distribuir
            </div>
          </q-banner>
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Costo unitario de agua por m³ (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" mask="###.###.###,####"
            reverse-fill-mask inputmode="decimal" :rules="[
              val => parseMaskedMoney(val) !== null || 'El costo unitario de agua es requerido'
            ]" v-model="formData.water_price_per_m3" 
            :hint="waterPriceReadonly ? 'Calculado automáticamente (Monto / Consumo dept + Común)' : 'Ingresa el costo unitario si no registras los totales'" />
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar</div>
          </q-btn>
        </div>
      </div>
    </q-form>

    <includeExpensesModal
      :dialog="showExpensesModal"
      :current-month="formData.month?.value || now.getMonth() + 1"
      :current-year="formData.year"
      :previously-selected-ids="selectedExpenseIds"
      @close-modal="showExpensesModal = false"
      @expenses-selected="onExpensesSelected"
    />

    <waterReadingsModal
      :dialog="showWaterReadingsModal"
      :readings="waterReadingsData"
      :total-consumption="totalWaterConsumption"
      :current-month="formData.month?.value || now.getMonth() + 1"
      :current-year="formData.year"
      @close-modal="showWaterReadingsModal = false"
    />
  </div>
</template>

<style lang="scss">
.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

.expenses-summary {
  background-color: #f4f4f5;
  border: 1px solid #e4e4e7;
  border-radius: 0.5rem;
}

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
