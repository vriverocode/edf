<script setup>
import { onMounted, ref, computed } from 'vue'
import { Notify } from 'quasar'
import { useAnnualBudgetStore } from '@/services/store/annualBudget.store'
import { useRoute, useRouter } from 'vue-router'
import createExpenseTemplateModal from '@/components/finance/createExpenseTemplateModal.vue'

const route = useRoute()
const router = useRouter()
const annualBudgetStore = useAnnualBudgetStore()

const loading = ref(false)
const saving = ref(false)
const loadingExpenses = ref(false)
const step = ref(1)
const isEdit = computed(() => !!route.params.id)
const showCreateModal = ref(false)

const form = ref({
  year: new Date().getFullYear(),
  name: '',
  status: 1,
})

const statusOptions = [
  { label: 'Borrador', value: 1 },
  { label: 'Activo', value: 2 },
  { label: 'Cerrado', value: 3 },
]

const availableExpenses = ref([])
const selectedExpenses = ref([])

const totalCalculated = computed(() => {
  return selectedExpenses.value.reduce((sum, e) => sum + (parseFloat(e.amount) || 0), 0)
})

const toggleExpense = (expense) => {
  const index = selectedExpenses.value.findIndex((e) => e.description === expense.description)
  if (index >= 0) {
    selectedExpenses.value.splice(index, 1)
  } else {
    selectedExpenses.value.push({
      description: expense.description,
      amount: expense.amount,
      expense_type: expense.expense_type,
      service_category_id: expense.service_category_id,
      sort_order: expense.sort_order,
    })
  }
}

const isSelected = (expense) => {
  return selectedExpenses.value.some((e) => e.description === expense.description)
}

const updateAmount = (expense, value) => {
  const selected = selectedExpenses.value.find((e) => e.description === expense.description)
  if (selected) {
    selected.amount = parseFloat(value) || 0
  }
}

const getSelectedAmount = (expense) => {
  const selected = selectedExpenses.value.find((e) => e.description === expense.description)
  return selected ? selected.amount : expense.amount
}

const onExpenseCreated = (expense) => {
  const exists = selectedExpenses.value.some(e => e.description === expense.description)
  if (!exists) {
    selectedExpenses.value.push({
      ...expense,
      sort_order: selectedExpenses.value.length,
    })
  }
}

const fetchAvailableExpenses = async () => {
  loadingExpenses.value = true
  try {
    const response = await annualBudgetStore.getAvailableExpenses()
    if (response?.code !== 200) throw response
    availableExpenses.value = response.data || []
  } catch (err) {
    const apiError = err?.error || err?.message || 'Error al cargar gastos disponibles'
    Notify.create({ color: 'negative', message: apiError, timeout: 2000 })
  } finally {
    loadingExpenses.value = false
  }
}

const fetchBudget = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const response = await annualBudgetStore.getAnnualBudget(route.params.id)
    if (response?.code !== 200) throw response
    const data = response.data
    form.value = {
      year: data.year,
      name: data.name,
      status: data.status,
    }
    selectedExpenses.value = (data.templates || []).map((t) => ({
      description: t.description,
      amount: t.amount,
      expense_type: t.expense_type,
      service_category_id: t.service_category_id,
      sort_order: t.sort_order,
    }))
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo cargar el presupuesto'
    Notify.create({ color: 'negative', message: apiError, timeout: 2000 })
    router.push('/admin/budget/annual')
  } finally {
    loading.value = false
  }
}

const goToStep2 = async () => {
  if (!form.value.year || !form.value.name) {
    Notify.create({ color: 'negative', message: 'Completa año y nombre', timeout: 2000 })
    return
  }
  step.value = 2
  if (availableExpenses.value.length === 0) {
    await fetchAvailableExpenses()
  }
}

const goToStep1 = () => {
  step.value = 1
}

const submit = async () => {
  if (selectedExpenses.value.length === 0) {
    Notify.create({ color: 'negative', message: 'Selecciona al menos un gasto', timeout: 2000 })
    return
  }
  saving.value = true
  try {
    const payload = {
      ...form.value,
      expenses: selectedExpenses.value,
    }
    let response
    if (isEdit.value) {
      response = await annualBudgetStore.updateAnnualBudget(route.params.id, payload)
    } else {
      response = await annualBudgetStore.createAnnualBudget(payload)
    }
    if (response?.code !== 200 && response?.code !== 201) throw response
    Notify.create({
      color: 'positive',
      message: isEdit.value ? 'Presupuesto actualizado correctamente' : 'Presupuesto creado correctamente',
      timeout: 2000,
    })
    router.push('/admin/budget/annual')
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo guardar el presupuesto'
    Notify.create({ color: 'negative', message: apiError, timeout: 2000 })
  } finally {
    saving.value = false
  }
}

const goTo = (url) => router.push(url)

onMounted(fetchBudget)
</script>

<template>
  <div class="md:px-20 px-2 pb-10 h-full" style="overflow: auto;">
    <!-- LOADING -->
    <div v-if="loading" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
    <!-- CONTENT -->
    <template v-else>
      <div class="text-center text-black text-h5 text-bold my-2">
        {{ isEdit ? 'Editar presupuesto anual' : 'Crear presupuesto anual' }}
      </div>
      <q-stepper
        v-model="step"
        header-nav
        color="primary"
        animated
        class="q-mt-md"
        style="background: transparent; box-shadow: none;"
      >
        <!-- STEP 1 -->
        <q-step :name="1" title="Datos básicos" icon="eva-edit-2-outline" :done="step > 1">
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-6">
              <div class="text-caption text-grey-7 q-mb-xs">Año</div>
              <q-input
                v-model.number="form.year"
                type="number"
                dense
                borderless
                class="form__inputsR"
                :rules="[(val) => !!val || 'Requerido']"
              />
            </div>
            <div class="col-12 col-md-6">
              <div class="text-caption text-grey-7 q-mb-xs">Nombre</div>
              <q-input
                v-model="form.name"
                dense
                borderless
                class="form__inputsR"
                placeholder="Ej: Presupuesto 2026"
                :rules="[(val) => !!val || 'Requerido']"
              />
            </div>
            <div class="col-12 col-md-6">
              <div class="text-caption text-grey-7 q-mb-xs">Estado</div>
              <q-select
                v-model="form.status"
                :options="statusOptions"
                emit-value
                map-options
                dense
                borderless
                class="form__inputsR"
              />
            </div>
          </div>
          <q-stepper-navigation>
            <div class="w-full">
              <q-btn color="primary" unelevated style="border-radius: 0.5rem; min-width: 150px;" @click="goToStep2" class="w-full">
                <div class="flex items-center py-1">
                  <div class="text-bold">Siguiente</div>
                </div>
              </q-btn>
            </div>
          </q-stepper-navigation>
        </q-step>

        <!-- STEP 2 -->
        <q-step :name="2" title="Seleccionar gastos" icon="eva-list-outline" :done="step > 2">
          <div class="text-subtitle1 text-grey-7 q-mb-sm">
            Selecciona los gastos recurrentes y ajusta los montos
          </div>

          <!-- LOADING EXPENSES -->
          <div v-if="loadingExpenses" class="flex justify-center py-10">
            <q-spinner-dots color="primary" size="3rem" />
          </div>

          <!-- EXPENSES LIST -->
          <template v-else>
            <div v-if="availableExpenses.length === 0" class="text-center text-grey-6 py-10">
              No hay gastos disponibles. Crea un presupuesto activo primero.
            </div>
            <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden">
              <div
                v-for="expense in availableExpenses"
                :key="expense.description"
                class="flex items-center q-pa-sm"
                :class="isSelected(expense) ? 'bg-blue-1' : ''"
                style="border-bottom: 1px solid #eee;"
              >
                <q-checkbox
                  :model-value="isSelected(expense)"
                  @update:model-value="toggleExpense(expense)"
                  color="primary"
                />
                <div class="col">
                  <div class="text-body2 font-medium">{{ expense.description }}</div>
                  <div class="text-caption text-grey-6" v-if="expense.service_category">
                    {{ expense.service_category.name }}
                  </div>
                </div>
                <div class="col-auto" style="min-width: 140px;">
                  <q-input
                    :model-value="getSelectedAmount(expense)"
                    @update:model-value="(val) => updateAmount(expense, val)"
                    dense
                    borderless
                    class="form__inputsRx"
                    mask="#.###.###,##"
                    reverse-fill-mask
                    inputmode="decimal"
                    :disable="!isSelected(expense)"
                    input-style="text-align: right;"
                  />
                </div>
              </div>

              <!-- TOTAL -->
              <div class="flex justify-end items-center q-pa-sm bg-grey-2">
                <div class="text-subtitle1 font-bold text-grey-9">
                  Total gastos seleccionados:
                  <span class="text-primary">S/. {{ totalCalculated.toFixed(2) }}</span>
                </div>
              </div>
            </div>

            <!-- CREAR NUEVOS GASTOS -->
            <div class="mt-4">
              <q-btn flat no-caps color="primary" icon="eva-plus-outline" label="Nuevo gasto" @click="showCreateModal = true" />
            </div>
          </template>

          <q-stepper-navigation class="q-mt-md">
            <div class="row w-full">
              <div class="col-4">
                <q-btn  color="grey-7" class="w-full" @click="goToStep1" style="border-radius: 0.5rem; ">
                  <div class="flex items-center py-1">
                    <div class=" text-bold pl-1">Volver</div>
                  </div>
                </q-btn>
              </div>
              <div class="col-8 pl-1">
                <q-btn
                  color="primary"
                  class="w-full "
                  unelevated
                  :loading="saving"
                  style="border-radius: 0.5rem; "
                  @click="submit"
                >
                  <div class="flex items-center py-1">
                    <div class=" text-bold pl-1">{{ isEdit ? 'Actualizar' : 'Crear' }}</div>
                  </div>
                </q-btn>
              </div>
            </div>
          </q-stepper-navigation>
        </q-step>
      </q-stepper>
    </template>

    <!-- MODAL CREAR GASTO -->
    <createExpenseTemplateModal
      :dialog="showCreateModal"
      @closeModal="showCreateModal = false"
      @created="onExpenseCreated"
    />
  </div>
</template>

<style lang="scss">
.q-stepper__step-inner{
  padding-top: 0px!important;
}
.q-stepper__header--border{
  border-bottom: none;
}
.form__inputsR .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>
