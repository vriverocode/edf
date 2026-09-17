<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { useDepartmentChargeStore } from '@/services/store/departmentCharge.store'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useExpenseStore } from '@/services/store/expense.store'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const chargeStore = useDepartmentChargeStore()
const apartmentStore = useApartmentStore()
const expenseStore = useExpenseStore()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const saving = ref(false)
const loadingDepartments = ref(false)
const loadingExpenses = ref(false)
const departments = ref([])
const expenses = ref([])

const form = ref({
  departament_id: null,
  expense_id: null,
  description: '',
  total_amount: 0,
  installments: 1,
  start_month: new Date().getMonth() + 1,
  start_year: new Date().getFullYear(),
})

const monthlyAmount = computed(() => {
  if (!form.value.installments || form.value.installments < 1) return 0
  return form.value.total_amount / form.value.installments
})

const monthOptions = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
].map((m, i) => ({ label: m, value: i + 1 }))

const onExpenseSelected = (expenseId) => {
  if (!expenseId) {
    form.value.description = ''
    form.value.total_amount = 0
    return
  }
  const expense = expenses.value.find(e => e.id === expenseId)
  if (expense) {
    form.value.description = expense.description || ''
    form.value.total_amount = expense.amount || 0
  }
}

const fetchDepartments = async () => {
  loadingDepartments.value = true
  try {
    const res = await apartmentStore.getApartmentsByFind('')
    departments.value = (res.data || []).map(d => ({
      label: `${d.number} — ${d.owner?.name || 'Sin propietario'}`,
      value: d.id,
    }))
  } catch (err) {
    console.log(err)
    Notify.create({ color: 'negative', message: 'Error al cargar departamentos' })
  } finally {
    loadingDepartments.value = false
  }
}

const fetchExpenses = async () => {
  loadingExpenses.value = true
  try {
    const res = await expenseStore.getExpenses({ expense_type: 2, status: 3 })
    expenses.value = (res.data || []).map(e => ({
      id: e.id,
      label: `${e.description} — S/. ${e.amount}`,
      value: e.id,
      amount: e.amount,
      description: e.description,
    }))
  } catch {
    Notify.create({ color: 'negative', message: 'Error al cargar gastos' })
  } finally {
    loadingExpenses.value = false
  }
}

const fetchCharge = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await chargeStore.getDepartmentCharge(route.params.id)
    const data = res.data
    form.value = {
      departament_id: data.departament_id,
      expense_id: data.expense_id,
      description: data.description,
      total_amount: data.total_amount,
      installments: data.installments,
      start_month: data.start_month,
      start_year: data.start_year,
    }
  } catch {
    Notify.create({ color: 'negative', message: 'Error al cargar cargo' })
    router.push('/admin/department-charges')
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  if (!form.value.departament_id || !form.value.description || !form.value.total_amount) {
    Notify.create({ color: 'negative', message: 'Completa todos los campos requeridos' })
    return
  }
  saving.value = true
  try {
    if (isEdit.value) {
      await chargeStore.updateDepartmentCharge(route.params.id, form.value)
      Notify.create({ color: 'positive', message: 'Cargo actualizado correctamente' })
    } else {
      await chargeStore.createDepartmentCharge(form.value)
      Notify.create({ color: 'positive', message: 'Cargo creado correctamente' })
    }
    router.push('/admin/department-charges')
  } catch (err) {
    Notify.create({ color: 'negative', message: err?.error || 'Error al guardar' })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchExpenses()])
  await fetchCharge()
})
</script>

<template>
  <div class="md:px-20 px-2 pb-10 h-full" style="overflow: auto;">
    <div v-if="loading" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
    <template v-else>
      <div class="text-center text-black text-h5 text-bold my-2">
        {{ isEdit ? 'Editar cargo extra' : 'Registrar cargo extra' }}
      </div>
      <div class="q-mt-md row ">
        <div class="col-12 col-md-6">
          <div class="text-caption text-grey-7 q-mb-xs">Departamento *</div>
          <q-select
            v-model="form.departament_id"
            :options="departments"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR"
            use-input
            input-debounce="300"
            @filter="(val, update) => update()"
            :loading="loadingDepartments"
            :rules="[(val) => !!val || 'Requerido']"
          />
        </div>
        <div class="col-12 col-md-6 mb-5">
          <div class="text-caption text-grey-7 q-mb-xs">Gasto vinculado (Extraordinario)</div>
          <q-select
            v-model="form.expense_id"
            :options="expenses"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR"
            use-input
            input-debounce="300"
            clearable
            @filter="(val, update) => update()"
            @update:model-value="onExpenseSelected"
            :loading="loadingExpenses"
            placeholder="Seleccionar gasto (opcional)"
          />
        </div>
        <div class="col-12">
          <div class="text-caption text-grey-7 q-mb-xs">Descripción *</div>
          <q-input
            v-model="form.description"
            dense
            borderless
            class="form__inputsR"
            placeholder="Ej: Reparación ascensor por inundación"
            :rules="[(val) => !!val || 'Requerido']"
          />
        </div>
        <div class="col-12 col-md-4">
          <div class="text-caption text-grey-7 q-mb-xs">Monto total (S/.) *</div>
          <q-input
            v-model.number="form.total_amount"
            type="number"
            dense
            borderless
            class="form__inputsR"
            :rules="[(val) => val > 0 || 'Debe ser mayor a 0']"
          />
        </div>
        <div class="col-12 col-md-4">
          <div class="text-caption text-grey-7 q-mb-xs">Cuotas *</div>
          <q-input
            v-model.number="form.installments"
            type="number"
            dense
            borderless
            class="form__inputsR"
            min="1"
            :rules="[(val) => val >= 1 || 'Mínimo 1']"
          />
        </div>
        <div class="col-12 col-md-4 mb-5">
          <div class="text-caption text-grey-7 q-mb-xs">Monto mensual</div>
          <q-input
            :model-value="monthlyAmount.toFixed(2)"
            dense
            borderless
            class="form__inputsR"
            disable
          />
        </div>
        <div class="col-12 col-md-6 mb-5">
          <div class="text-caption text-grey-7 q-mb-xs">Mes de inicio *</div>
          <q-select
            v-model="form.start_month"
            :options="monthOptions"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR"
          />
        </div>
        <div class="col-12 col-md-6">
          <div class="text-caption text-grey-7 q-mb-xs">Año de inicio *</div>
          <q-input
            v-model.number="form.start_year"
            type="number"
            dense
            borderless
            class="form__inputsR"
            min="2020"
          />
        </div>
      </div>
      <div class="flex justify-end q-mt-md">
        <q-btn flat color="grey-7" label="Cancelar" class="q-mr-md" @click="router.push('/admin/department-charges')" />
        <q-btn color="primary" unelevated :loading="saving" style="border-radius: 0.5rem; min-width: 150px;" @click="submit">
          <div class="flex items-center py-1">
            <div class="q-pt-xs text-bold pl-1">{{ isEdit ? 'Actualizar' : 'Crear' }}</div>
          </div>
        </q-btn>
      </div>
    </template>
  </div>
</template>

<style lang="scss">
.form__inputsR .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>
