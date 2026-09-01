<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import includeExpensesModal from '@/components/monthlyBills/includeExpensesModal.vue'

const route = useRoute()
const router = useRouter()
const monthlyBillsStore = useMonthlyBillsStore()

const loading = ref(false)
const loadingData = ref(false)

const parseMaskedMoney = (value, decimal = 2) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(decimal))
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

const billId = computed(() => route.params.id || route.query.id)

const formData = ref({
  month: null,
  year: new Date().getFullYear(),
  monthly_budget: '',
  total_maintenance_budget: '',
  total_water_bill_amount: '',
  total_water_consumption_m3: null,
  water_price_per_m3: '',
  common_water_consumption_m3: null
})

const billExpenses = ref([])
const showExpenseModal = ref(false)
const selectedExpenseIds = ref([])

const hasWaterTotals = computed(() => {
  const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
  const consumption = Number(formData.value.total_water_consumption_m3)
  return amount !== null && amount > 0 && Number.isFinite(consumption) && consumption > 0
})

const waterPriceReadonly = computed(() => hasWaterTotals.value)

const expensesTotal = computed(() => {
  return billExpenses.value.reduce((sum, e) => sum + Number(e.amount || 0), 0)
})

const commonWaterCost = computed(() => {
  const consumption = Number(formData.value.common_water_consumption_m3) || 0
  const price = parseMaskedMoney(formData.value.water_price_per_m3, 4) || 0
  return Number((consumption * price).toFixed(2))
})

const computedTotal = computed(() => {
  const budget = parseMaskedMoney(formData.value.monthly_budget) || 0
  return Number((budget + expensesTotal.value + commonWaterCost.value).toFixed(2))
})

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

watch(
  () => [formData.value.monthly_budget, formData.value.common_water_consumption_m3, formData.value.water_price_per_m3],
  () => {
    formData.value.total_maintenance_budget = formatMaskedMoney(computedTotal.value)
  }
)

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const loadBill = async () => {
  if (!billId.value) return
  loadingData.value = true
  try {
    const response = await monthlyBillsStore.getMonthlyBillById(billId.value)
    const bill = response.data

    formData.value.month = monthOptions.find(m => m.value === bill.month) || null
    formData.value.year = bill.year
    formData.value.monthly_budget = bill.monthly_budget !== null ? formatMaskedMoney(bill.monthly_budget) : ''
    formData.value.total_maintenance_budget = formatMaskedMoney(bill.total_maintenance_budget)
    formData.value.total_water_bill_amount = bill.total_water_bill_amount === null ? '' : formatMaskedMoney(bill.total_water_bill_amount)
    formData.value.total_water_consumption_m3 = bill.total_water_consumption_m3
    formData.value.water_price_per_m3 = formatMaskedMoney(bill.water_price_per_m3, 4)
    formData.value.common_water_consumption_m3 = bill.common_water_consumption_m3

    billExpenses.value = bill.expenses || []
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo cargar el presupuesto')
  } finally {
    loadingData.value = false
  }
}

const openExpenseModal = () => {
  selectedExpenseIds.value = billExpenses.value.map(e => e.id)
  showExpenseModal.value = true
}

const onExpensesSelected = ({ expenseIds, expenses }) => {
  const currentIds = billExpenses.value.map(b => b.id)
  const toRemove = currentIds.filter(id => !expenseIds.includes(id))
  const toAdd = expenseIds.filter(id => !currentIds.includes(id))

  billExpenses.value = billExpenses.value.filter(b => !toRemove.includes(b.id))

  toAdd.forEach(id => {
    const fromModal = expenses.find(e => e.id === id)
    if (fromModal) {
      billExpenses.value.push({
        id: fromModal.id,
        provider: { name: fromModal.provider_name },
        amount: fromModal.amount,
        description: '',
        invoice_number: ''
      })
    }
  })

  selectedExpenseIds.value = [...expenseIds]
  formData.value.total_maintenance_budget = formatMaskedMoney(computedTotal.value)
}

const unlinkExpense = (expenseId) => {
  billExpenses.value = billExpenses.value.filter(e => e.id !== expenseId)
  selectedExpenseIds.value = billExpenses.value.map(e => e.id)
  formData.value.total_maintenance_budget = formatMaskedMoney(computedTotal.value)
  showNotify('info', 'Gasto desvinculado')
}

const submit = async () => {
  if (!billId.value) return
  loading.value = true
  try {
    const payload = {
      month: formData.value.month?.value,
      year: Number(formData.value.year),
      monthly_budget: parseMaskedMoney(formData.value.monthly_budget),
      total_maintenance_budget: parseMaskedMoney(formData.value.total_maintenance_budget),
      total_water_bill_amount: parseMaskedMoney(formData.value.total_water_bill_amount),
      total_water_consumption_m3:
        formData.value.total_water_consumption_m3 === null || formData.value.total_water_consumption_m3 === ''
          ? null
          : Number(formData.value.total_water_consumption_m3),
      water_price_per_m3: parseMaskedMoney(formData.value.water_price_per_m3, 4),
      common_water_consumption_m3: formData.value.common_water_consumption_m3
        ? Number(formData.value.common_water_consumption_m3)
        : null,
      expense_ids: billExpenses.value.map(e => e.id)
    }

    const response = await monthlyBillsStore.updateMonthlyBill(billId.value, payload)
    if (response?.code !== 200) throw response

    showNotify('positive', 'Presupuesto mensual actualizado con éxito')
    router.go(-1)
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo actualizar el presupuesto')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadBill()
})
</script>

<template>
  <div class="md:px-20 px-2 pb-8 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Editar presupuesto mensual
    </div>

    <div v-if="loadingData" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>

    <q-form v-else @submit="submit()">
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
          <div class="text-subtitle2 text-black">Presupuesto mensual base (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.monthly_budget" mask="###.###.###,##" reverse-fill-mask inputmode="decimal"
            :rules="[val => parseMaskedMoney(val) !== null || 'El presupuesto base es requerido']" />
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto total recibo de agua (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_water_bill_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo total de agua (m³)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number" step="0.001"
            v-model.number="formData.total_water_consumption_m3" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Costo unitario de agua por m³ (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.water_price_per_m3" mask="###.###.###,####" reverse-fill-mask inputmode="decimal"
            :rules="[val => parseMaskedMoney(val) !== null || 'El costo unitario de agua es requerido']"
            :hint="waterPriceReadonly ? 'Calculado automáticamente (Monto / Consumo)' : 'Ingresa el costo unitario si no registras los totales'" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo áreas comunes (m³)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number" step="0.001"
            v-model.number="formData.common_water_consumption_m3" />
        </div>

        <div class="col-12 mt-4 px-2 md:px-12">
          <div class="flex justify-between items-center mb-2">
            <div class="text-subtitle2 text-black font-bold">Gastos vinculados</div>
            <q-btn color="primary" outline size="sm" @click="openExpenseModal" icon="eva-plus-outline" label="Agregar gasto" />
          </div>

          <div v-if="billExpenses.length === 0" class="text-grey-6 text-caption py-2">
            No hay gastos vinculados a este presupuesto.
          </div>

          <q-table v-else :rows="billExpenses" :columns="[
            { name: 'provider', label: 'Proveedor', field: row => row.provider?.name || '-', align: 'left' },
            { name: 'description', label: 'Descripción', field: 'description', align: 'left' },
            { name: 'invoice', label: 'N° Factura', field: 'invoice_number', align: 'left' },
            { name: 'amount', label: 'Monto', field: 'amount', align: 'right', format: v => 'S/ ' + Number(v).toFixed(2) },
            { name: 'actions', label: '', field: '', align: 'center' }
          ]" flat dense :pagination="{ rowsPerPage: 0 }" hide-bottom>
            <template v-slot:body-cell-actions="props">
              <q-td :props="props">
                <q-btn flat dense round icon="eva-close-outline" size="sm" color="negative"
                  @click="unlinkExpense(props.row.id)" />
              </q-td>
            </template>
          </q-table>

          <div v-if="billExpenses.length > 0" class="text-right text-subtitle2 text-black mt-1 md:pr-5">
            Sub-total gastos: S/ {{ expensesTotal.toFixed(2) }}
          </div>
        </div>

        <div class="col-12 mt-3 px-2 md:px-12">
          <div class="bg-grey-2 rounded-lg p-3">
            <div class="flex justify-between mb-1">
              <span class="text-grey-7">Presupuesto base</span>
              <span class="font-medium">S/ {{ formatMaskedMoney(parseMaskedMoney(formData.monthly_budget)) || '0,00' }}</span>
            </div>
            <div class="flex justify-between mb-1">
              <span class="text-grey-7">+ Gastos ({{ billExpenses.length }})</span>
              <span class="font-medium">S/ {{ expensesTotal.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between mb-1">
              <span class="text-grey-7">+ Agua común ({{ formData.common_water_consumption_m3 || 0 }} m³)</span>
              <span class="font-medium">S/ {{ commonWaterCost.toFixed(2) }}</span>
            </div>
            <q-separator class="my-1" />
            <div class="flex justify-between">
              <span class="text-bold">Total a distribuir</span>
              <span class="text-bold text-primary">S/ {{ formatMaskedMoney(computedTotal) || '0,00' }}</span>
            </div>
          </div>
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="grey-7" flat class="mr-2" @click="router.go(-1)">Cancelar</q-btn>
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar cambios</div>
          </q-btn>
        </div>
      </div>
    </q-form>

    <includeExpensesModal
      :dialog="showExpenseModal"
      :current-month="formData.month?.value || new Date().getMonth() + 1"
      :current-year="formData.year"
      :previously-selected-ids="selectedExpenseIds"
      @close-modal="showExpenseModal = false"
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

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
