<script setup>
import { computed, ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useRouter } from 'vue-router'
import includeExpensesModal from '@/components/monthlyBills/includeExpensesModal.vue'


const loading = ref(false)
const monthlyBillsStore = useMonthlyBillsStore()
const router = useRouter();
const showExpensesModal = ref(false)
const selectedExpenseIds = ref([])
const selectedExpensesData = ref([])
const previousSelectedTotal = ref(0)
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

const now = new Date()
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

const progressPercent = computed(() => {
  const total = parseMaskedMoney(formData.value.total_maintenance_budget) || 0
  if (total === 0) return 0
  const spent = previousSelectedTotal.value
  return Math.min(100, Math.round((spent / total) * 100))
})

watch(() => formData.value.month, loadPreviousMonthData)

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
  total_maintenance_budget: '',
  total_water_bill_amount: '',
  total_water_consumption_m3: null,
  water_price_per_m3: ''
})

const hasWaterTotals = computed(() => {
  const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
  const consumption = Number(formData.value.total_water_consumption_m3)
  return amount !== null && amount > 0 && Number.isFinite(consumption) && consumption > 0
})

const waterPriceReadonly = computed(() => hasWaterTotals.value)

watch(
  () => [formData.value.total_water_bill_amount, formData.value.total_water_consumption_m3],
  () => {
    if (!hasWaterTotals.value) return
    const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const consumption = Number(formData.value.total_water_consumption_m3)
    if (amount === null || !Number.isFinite(consumption) || consumption <= 0) return
    const computedPrice = amount / consumption

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
  const currentBudget = parseMaskedMoney(formData.value.total_maintenance_budget) || 0
  const diff = totalAmount - previousSelectedTotal.value
  const newBudget = currentBudget + diff
  formData.value.total_maintenance_budget = formatMaskedMoney(newBudget)
  selectedExpenseIds.value = expenseIds
  selectedExpensesData.value = expenses
  previousSelectedTotal.value = totalAmount
}

const submit = async () => {
  loading.value = true
  try {
    const totalMaintenanceBudget = parseMaskedMoney(formData.value.total_maintenance_budget)
    const totalWaterBillAmount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const waterPricePerM3 = parseMaskedMoney(formData.value.water_price_per_m3)

    const waterConsumption = formData.value.total_water_consumption_m3 === null || formData.value.total_water_consumption_m3 === '' ? null : parseMaskedMoney(formData.value.total_water_consumption_m3)
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
      total_maintenance_budget: totalMaintenanceBudget,
      total_water_bill_amount: totalWaterBillAmount,
      total_water_consumption_m3: formData.value.total_water_consumption_m3 === null || formData.value.total_water_consumption_m3 === '' ? null : parseMaskedMoney(formData.value.total_water_consumption_m3),
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

        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Presupuesto total a distribuir (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_maintenance_budget" mask="###.###.###,##" reverse-fill-mask inputmode="decimal"
            :rules="[
              val => parseMaskedMoney(val) !== null || 'El presupuesto total es requerido'
            ]" />
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
            <div class="text-caption text-grey-7 q-mb-xs">Gastos incluidos:</div>
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
        <div v-if="progressPercent > 0" class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black q-mb-xs">Avance del presupuesto</div>
          <q-linear-progress :value="progressPercent / 100" color="primary" class="q-mt-xs" size="24px"
            style="border-radius: 4px;">
            <div class="absolute-full flex flex-center text-white text-bold text-body2">
              {{ progressPercent }}%
            </div>
          </q-linear-progress>
          <div class="text-caption text-grey-6 q-mt-xs">
            S/. {{ formatMaskedMoney(previousSelectedTotal) }} de S/. {{ formatMaskedMoney(parseMaskedMoney(formData.value.total_maintenance_budget) || 0) }}
          </div>
        </div>
        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto total recibo de agua (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_water_bill_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo total de agua (m³)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" mask="#.##0,####"
            reverse-fill-mask inputmode="decimal" v-model="formData.total_water_consumption_m3" />
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Costo unitario de agua por m³ (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" mask="###.###.###,####"
            reverse-fill-mask inputmode="decimal" :rules="[
              val => parseMaskedMoney(val) !== null || 'El costo unitario de agua es requerido'
            ]" v-model="formData.water_price_per_m3" :readonly="waterPriceReadonly"
            :hint="waterPriceReadonly ? 'Calculado automáticamente (Monto / Consumo)' : 'Ingresa el costo unitario si no registras los totales'" />
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
