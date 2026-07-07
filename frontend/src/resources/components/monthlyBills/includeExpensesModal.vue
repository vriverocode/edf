<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useExpenseStore } from '@/services/store/expense.store'

const expenseStore = useExpenseStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  currentMonth: {
    type: Number,
    default: () => new Date().getMonth() + 1
  },
  currentYear: {
    type: Number,
    default: () => new Date().getFullYear()
  },
  previouslySelectedIds: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['closeModal', 'expensesSelected'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const expenses = ref([])
const selectedIds = ref([])

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

const filterMonth = ref(props.currentMonth)
const filterYear = ref(props.currentYear)

const totalSelected = ref(0)

const formatMoney = (value) => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0.00'
  const fixed = n.toFixed(2)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2200
  })
}

const close = () => {
  emit('closeModal')
}

const loadExpenses = async () => {
  loading.value = true
  expenses.value = []
  selectedIds.value = []
  totalSelected.value = 0

  try {
    const response = await expenseStore.getUnassignedExpenses(filterMonth.value, filterYear.value)
    expenses.value = response?.data?.expenses || []

    const validIds = expenses.value.map((e) => e.id)
    selectedIds.value = props.previouslySelectedIds.filter((id) => validIds.includes(id))
    calculateTotal()
  } catch (error) {
    showNotify('negative', error || 'Error al cargar gastos')
  } finally {
    loading.value = false
  }
}

const toggleExpense = (expense) => {
  const index = selectedIds.value.indexOf(expense.id)
  if (index === -1) {
    selectedIds.value.push(expense.id)
  } else {
    selectedIds.value.splice(index, 1)
  }
  calculateTotal()
}

const isSelected = (id) => {
  return selectedIds.value.includes(id)
}

const calculateTotal = () => {
  totalSelected.value = expenses.value
    .filter((e) => selectedIds.value.includes(e.id))
    .reduce((sum, e) => sum + Number(e.amount), 0)
}

const selectAll = () => {
  if (selectedIds.value.length === expenses.value.length) {
    selectedIds.value = []
  } else {
    selectedIds.value = expenses.value.map((e) => e.id)
  }
  calculateTotal()
}

const submit = () => {
  const selectedExpenses = expenses.value
    .filter((e) => selectedIds.value.includes(e.id))
    .map((e) => ({
      id: e.id,
      provider_name: e.provider?.name || 'Sin proveedor',
      amount: Number(e.amount)
    }))

  emit('expensesSelected', {
    totalAmount: totalSelected.value,
    expenseIds: [...selectedIds.value],
    expenses: selectedExpenses
  })
  close()
}

watch(
  () => props.dialog,
  (open) => {
    dialogVisible.value = open
    if (open) {
      filterMonth.value = props.currentMonth
      filterYear.value = props.currentYear
      loadExpenses()
    }
  }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})

watch([filterMonth, filterYear], () => {
  if (dialogVisible.value) {
    loadExpenses()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close">
    <q-card style="min-width: min(520px, 92vw); max-height: 80vh;" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Incluir gastos</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Selecciona los gastos del mes para incluirlos en el presupuesto total a distribuir.
      </div>

      <div class="row q-col-gutter-sm q-mb-md">
        <div class="col-6">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select
            v-model="filterMonth"
            :options="monthOptions"
            option-label="name"
            option-value="value"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
            color="primary"
          />
        </div>
        <div class="col-6">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input
            v-model.number="filterYear"
            dense
            borderless
            type="number"
            class="form__inputsR mt-1"
            color="primary"
          />
        </div>
      </div>

      <div v-if="loading" class="text-center q-py-lg">
        <q-spinner-dots size="30px" color="primary" />
        <div class="text-caption text-grey-7 q-mt-sm">Cargando gastos...</div>
      </div>

      <div v-else-if="expenses.length === 0" class="text-center q-py-lg">
        <div class="text-caption text-grey-7">
          No hay gastos sin asignar para este periodo.
        </div>
      </div>

      <div v-else>
        <div class="row items-center q-mb-sm">
          <q-checkbox
            :model-value="selectedIds.length === expenses.length"
            @update:model-value="selectAll"
            label="Seleccionar todos"
            color="primary"
            class="text-caption"
          />
          <q-space />
          <div class="text-caption text-grey-7">
            {{ selectedIds.length }} de {{ expenses.length }} seleccionados
          </div>
        </div>

        <div class="expenses-list" style="max-height: 300px; overflow-y: auto;">
          <div
            v-for="expense in expenses"
            :key="expense.id"
            class="expense-item row items-center q-pa-sm q-mb-xs"
            :class="{ 'expense-selected': isSelected(expense.id) }"
            @click="toggleExpense(expense)"
          >
            <q-checkbox
              :model-value="isSelected(expense.id)"
              @update:model-value="toggleExpense(expense)"
              color="primary"
              class="col-auto"
            />
            <div class="col">
              <div class="text-body2 text-black text-weight-medium">
                {{ expense.provider?.name || 'Sin proveedor' }}
              </div>
              <div class="text-caption text-grey-7">
                {{ expense.description }}
              </div>
              <div class="text-caption text-grey-7">
                Fecha: {{ expense.issue_date }}
              </div>
            </div>
            <div class="col-auto text-right">
              <div class="text-body2 text-weight-bold" style="color: #18181b;">
                S/ {{ formatMoney(expense.amount) }}
              </div>
            </div>
          </div>
        </div>

        <div class="total-box q-mt-md q-pa-sm">
          <div class="row items-center">
            <div class="text-subtitle2 text-black">Total seleccionado:</div>
            <q-space />
            <div class="text-h6 text-weight-bold" style="color: #18181b;">
              S/ {{ formatMoney(totalSelected) }}
            </div>
          </div>
        </div>
      </div>

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" no-caps @click="close" />
        <q-btn
          color="primary"
          label="Guardar"
          no-caps
          :loading="loading"
          @click="submit"
        />
      </div>
    </q-card>
  </q-dialog>
</template>

<style lang="scss" scoped>
.form__inputsR {
  & :deep(.q-field__inner) {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

.expense-item {
  border: 1px solid rgb(223, 223, 223);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background-color: #f4f4f5;
  }

  &.expense-selected {
    background-color: #f0f7ff;
    border-color: #1976d2;
  }
}

.total-box {
  background-color: #f4f4f5;
  border: 1px solid #e4e4e7;
  border-radius: 0.5rem;
}
</style>
