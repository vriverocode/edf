<script setup>
import { ref, watch, computed } from 'vue'
import { Notify } from 'quasar'
import { useAnnualBudgetStore } from '@/services/store/annualBudget.store'

const annualBudgetStore = useAnnualBudgetStore()

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
  previouslySelected: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['closeModal', 'expensesSelected'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const templates = ref([])
const selectedExpenses = ref([])

const monthNames = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

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

const totalSelected = computed(() => {
  return selectedExpenses.value.reduce((sum, e) => sum + Number(e.amount), 0)
})

const loadTemplates = async () => {
  loading.value = true
  templates.value = []
  selectedExpenses.value = []

  try {
    const response = await annualBudgetStore.getAvailableExpenses()
    const data = response?.data || []
    templates.value = data

    // Pre-seleccionar todos los templates con su monthly_amount
    selectedExpenses.value = data.map((t) => ({
      template_id: t.id,
      description: t.description,
      service_category: t.service_category?.name || 'Sin categoría',
      amount: Number(t.monthly_amount || t.amount),
      sort_order: t.sort_order
    }))
  } catch (error) {
    showNotify('negative', error || 'Error al cargar plantillas del presupuesto anual')
  } finally {
    loading.value = false
  }
}

const updateAmount = (templateId, newAmount) => {
  const expense = selectedExpenses.value.find((e) => e.template_id === templateId)
  if (expense) {
    expense.amount = Number(newAmount) || 0
  }
}

const toggleTemplate = (template) => {
  const index = selectedExpenses.value.findIndex((e) => e.template_id === template.id)
  if (index === -1) {
    selectedExpenses.value.push({
      template_id: template.id,
      description: template.description,
      service_category: template.service_category?.name || 'Sin categoría',
      amount: Number(template.monthly_amount || template.amount),
      sort_order: template.sort_order
    })
  } else {
    selectedExpenses.value.splice(index, 1)
  }
}

const isSelected = (templateId) => {
  return selectedExpenses.value.some((e) => e.template_id === templateId)
}

const submit = () => {
  if (selectedExpenses.value.length === 0) {
    showNotify('warning', 'Selecciona al menos un gasto')
    return
  }

  emit('expensesSelected', {
    totalAmount: totalSelected.value,
    expenses: selectedExpenses.value.map((e) => ({
      template_id: e.template_id,
      description: e.description,
      amount: e.amount
    }))
  })
  close()
}

watch(
  () => props.dialog,
  (open) => {
    dialogVisible.value = open
    if (open) {
      loadTemplates()
    }
  }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close">
    <q-card style="min-width: min(580px, 92vw); max-height: 85vh;" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Gastos del presupuesto anual</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Gastos precargados del presupuesto anual para {{ monthNames[props.currentMonth] }} {{ props.currentYear }}.
        Puedes modificar montos antes de guardar.
      </div>

      <div v-if="loading" class="text-center q-py-lg">
        <q-spinner-dots size="30px" color="primary" />
        <div class="text-caption text-grey-7 q-mt-sm">Cargando plantillas...</div>
      </div>

      <div v-else-if="templates.length === 0" class="text-center q-py-lg">
        <div class="text-caption text-grey-7">
          No hay gastos configurados en el presupuesto anual activo.
        </div>
      </div>

      <div v-else>
        <div class="expenses-list" style="max-height: 400px; overflow-y: auto;">
          <div
            v-for="template in templates"
            :key="template.id"
            class="expense-item q-pa-sm q-mb-xs"
            :class="{ 'expense-selected': isSelected(template.id) }"
          >
            <div class="row items-center">
              <div class="col-auto">
                <q-checkbox
                  :model-value="isSelected(template.id)"
                  @update:model-value="toggleTemplate(template)"
                  color="primary"
                />
              </div>
              <div class="col">
                <div class="text-body2 text-black text-weight-medium">
                  {{ template.description }}
                </div>
                <div class="text-caption text-grey-7">
                  {{ template.service_category?.name || 'Sin categoría' }}
                </div>
              </div>
              <div class="col-auto" style="min-width: 130px;">
                <q-input
                  v-if="isSelected(template.id)"
                  :model-value="selectedExpenses.find((e) => e.template_id === template.id)?.amount"
                  @update:model-value="updateAmount(template.id, $event)"
                  dense
                  borderless
                  type="number"
                  step="0.01"
                  min="0"
                  class="form__inputsR"
                  color="primary"
                  prefix="S/"
                  input-class="text-right text-weight-bold"
                />
                <div v-else class="text-body2 text-weight-bold text-right" style="color: #18181b;">
                  S/ {{ formatMoney(template.monthly_amount || template.amount) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="total-box q-mt-md q-pa-sm">
          <div class="row items-center">
            <div class="text-subtitle2 text-black">Total mensual:</div>
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
          label="Guardar gastos"
          no-caps
          :loading="loading"
          :disable="selectedExpenses.length === 0"
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
    padding: 0px 0.5rem;
  }
}

.expense-item {
  border: 1px solid rgb(223, 223, 223);
  border-radius: 0.5rem;
  transition: all 0.15s ease;

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
