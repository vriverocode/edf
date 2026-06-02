<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '@/services/store/expense.store'
import createProviderModal from '@/components/finance/createProviderModal.vue'

const route = useRoute()
const router = useRouter()
const expenseStore = useExpenseStore()

const loading = ref(false)
const loadingData = ref(false)
const step = ref(1)
const expenseForm = ref(null)
const providers = ref([])
const serviceCategories = ref([])
const createProviderDialog = ref(false)

const isEdit = computed(() => !!route.params.id)
const expenseId = computed(() => route.params.id)

const expenseTypeOptions = [
  { value: 1, name: 'Ordinario / Recurrente' },
  { value: 2, name: 'Extraordinario' }
]

const parseMaskedMoney = (value) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(2))
}

const formatMaskedMoney = (value) => {
  if (value === null || value === undefined) return ''
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return ''
  const fixed = n.toFixed(2)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const formData = ref({
  provider: null,
  invoice_number: '',
  amount: '',
  issue_date: '',
  due_date: '',
  expense_type: expenseTypeOptions[0],
  location_scope: '',
  unit: '',
  description: '',
  attachment_url: ''
})

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const loadFormOptions = async () => {
  const response = await expenseStore.getExpenseFormOptions()
  if (response?.code !== 200) throw response
  providers.value = response.data?.providers || []
  serviceCategories.value = response.data?.service_categories || []
}

const onProviderCreated = (created) => {
  if (!created?.id) return
  const exists = providers.value.some((p) => p.id === created.id)
  if (!exists) {
    providers.value = [...providers.value, created]
  }
  formData.value.provider = providers.value.find((p) => p.id === created.id) || created
}

const loadExpense = async () => {
  if (!isEdit.value) return
  const response = await expenseStore.getExpenseById(expenseId.value)
  if (response?.code !== 200) throw response
  const expense = response.data

  formData.value = {
    provider: providers.value.find((p) => p.id === expense.provider_id) || null,
    invoice_number: expense.invoice_number || '',
    amount: formatMaskedMoney(expense.amount),
    issue_date: expense.issue_date ? expense.issue_date.substring(0, 10) : '',
    due_date: expense.due_date ? expense.due_date.substring(0, 10) : '',
    expense_type: expenseTypeOptions.find((t) => t.value === expense.expense_type) || expenseTypeOptions[0],
    location_scope: expense.location_scope || '',
    unit: expense.unit || '',
    description: expense.description || '',
    attachment_url: expense.attachment_url || ''
  }
}

const buildPayload = () => ({
  provider_id: formData.value.provider?.id,
  invoice_number: formData.value.invoice_number || null,
  amount: parseMaskedMoney(formData.value.amount),
  issue_date: formData.value.issue_date,
  due_date: formData.value.due_date,
  expense_type: formData.value.expense_type?.value,
  location_scope: formData.value.location_scope || null,
  unit: formData.value.unit || null,
  description: formData.value.description,
  attachment_url: formData.value.attachment_url || null
})

const saveExpense = async () => {
  loading.value = true
  try {
    const payload = buildPayload()
    const response = isEdit.value
      ? await expenseStore.updateExpense(expenseId.value, payload)
      : await expenseStore.createExpense(payload)

    if (response?.code !== 200) throw response

    showNotify('positive', isEdit.value ? 'Gasto actualizado con éxito' : 'Gasto registrado con éxito')
    setTimeout(() => router.push('/admin/expenses/list'), 800)
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo guardar el gasto'
    showNotify('negative', apiError)
  } finally {
    loading.value = false
  }
}

/** Un solo submit: en paso 1 valida facturación y avanza; en paso 2 valida servicio y guarda. */
const submit = async () => {
  const valid = await expenseForm.value?.validate()
  if (!valid) return

  if (step.value === 1) {
    step.value = 2
    return
  }

  await saveExpense()
}

const goBack = () => {
  step.value = 1
}

onMounted(async () => {
  loadingData.value = true
  try {
    await loadFormOptions()
    await loadExpense()
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'Error al cargar el formulario')
    if (isEdit.value) router.push('/admin/expenses/list')
  } finally {
    loadingData.value = false
  }
})
</script>

<template>
  <div class="md:px-20 md:mx-16 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      {{ isEdit ? 'Editar gasto' : 'Registrar gasto' }}
    </div>

    <div v-if="loadingData" class="flex justify-center py-20">
      <q-spinner-dots color="primary" size="5rem" />
    </div>

    <q-form v-else ref="expenseForm" @submit.prevent="submit">
      <!-- <div class="flex justify-center gap-4 mb-6 px-2">
        <div
          class="expense-step"
          :class="{ 'expense-step--active': step === 1, 'expense-step--done': step > 1 }"
        >
          <span class="expense-step__number">1</span>
          <span class="expense-step__label">Facturación</span>
        </div>
        <div class="expense-step__line" :class="{ 'expense-step__line--active': step > 1 }" />
        <div class="expense-step" :class="{ 'expense-step--active': step === 2 }">
          <span class="expense-step__number">2</span>
          <span class="expense-step__label">Servicio</span>
        </div>
      </div> -->

      <!-- Parte 1: facturación -->
      <div v-show="step === 1" class="row w-full">
        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="row q-col-gutter-sm items-center">
            <div class="col">
              <div class="text-subtitle2 text-black">Proveedor*</div>
              <q-select
                dense
                borderless
                class="form__inputsR mt-1"
                v-model="formData.provider"
                :options="providers"
                option-label="name"
                :rules="[val => !!val || 'El proveedor es requerido']"
                lazy-rules
              />
            </div>
            <div class="col-auto">
              <q-btn flat dense round color="primary" type="button" @click="createProviderDialog = true">
                <q-icon name="eva-plus-outline" />
                <q-tooltip>Nuevo proveedor</q-tooltip>
              </q-btn>
            </div>
          </div>
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">N° de factura</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.invoice_number" />
        </div>

        <div class="col-md-6 col-12 mt-5 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto (S/.)*</div>
          <q-input
            dense
            borderless
            clearable
            class="form__inputsR mt-1"
            v-model="formData.amount"
            mask="###.###.###,##"
            reverse-fill-mask
            inputmode="decimal"
            :rules="[val => parseMaskedMoney(val) !== null || 'El monto es requerido']"
            lazy-rules
          />
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Fecha de emisión</div>
          <q-input
            dense
            borderless
            class="form__inputsR mt-1"
            type="date"
            v-model="formData.issue_date"
            :rules="[val => !!val || 'La fecha de emisión es requerida']"
            lazy-rules
          />
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Fecha de vencimiento</div>
          <q-input
            dense
            borderless
            class="form__inputsR mt-1"
            type="date"
            v-model="formData.due_date"
            :rules="[val => !!val || 'La fecha de vencimiento es requerida']"
            lazy-rules
          />
        </div>
      </div>

      <!-- Parte 2: servicio (reglas solo activas en paso 2) -->
      <div v-show="step === 2" class="row w-full">
        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Tipo de gasto</div>
          <q-select
            dense
            borderless
            class="form__inputsR mt-1"
            v-model="formData.expense_type"
            :options="expenseTypeOptions"
            option-label="name"
            :rules="step === 2 ? [val => !!val || 'El tipo es requerido'] : []"
            lazy-rules
          />
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Ámbito / ubicación</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.location_scope" />
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Unidad / elemento</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.unit" />
        </div>

        <div class="col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Descripción del servicio</div>
          <q-input
            dense
            borderless
            type="textarea"
            autogrow
            class="form__inputsR mt-1"
            v-model="formData.description"
            :rules="step === 2 ? [val => !!val?.trim() || 'La descripción es requerida'] : []"
            lazy-rules
          />
        </div>

        <div class="col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">URL del adjunto (opcional)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.attachment_url" />
        </div>
      </div>

      <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4 gap-2">
        <q-btn
          v-if="step === 2"
          outline
          color="grey-7"
          style="border-radius: 0.5rem;"
          type="button"
          @click="goBack"
        >
          <div class="px-6 py-1">Atrás</div>
        </q-btn>
        <q-btn
          v-else
          outline
          color="grey-7"
          style="border-radius: 0.5rem;"
          type="button"
          @click="router.push('/admin/expenses/list')"
        >
          <div class="px-6 py-1">Cancelar</div>
        </q-btn>
        <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading && step === 2">
          <div class="px-10 py-1">{{ step === 1 ? 'Siguiente' : 'Guardar' }}</div>
        </q-btn>
      </div>
    </q-form>

    <createProviderModal
      :dialog="createProviderDialog"
      :service-categories="serviceCategories"
      @close-modal="createProviderDialog = false"
      @created="onProviderCreated"
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

.expense-step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  opacity: 0.45;

  &--active,
  &--done {
    opacity: 1;
  }

  &__number {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.875rem;
    background: #e0e0e0;
    color: #616161;
  }

  &--active .expense-step__number,
  &--done .expense-step__number {
    background: var(--q-primary);
    color: #fff;
  }

  &__label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #424242;
  }
}

.expense-step__line {
  width: 3rem;
  height: 2px;
  background: #e0e0e0;
  align-self: center;
  margin-top: -1rem;

  &--active {
    background: var(--q-primary);
  }
}
</style>
