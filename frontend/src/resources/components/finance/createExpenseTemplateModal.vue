<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import ApiService from '@/services/axios'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'
import createServiceCategoryModal from '@/components/finance/createServiceCategoryModal.vue'

const serviceCategoryStore = useServiceCategoryStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  budgetId: {
    type: [Number, String, null],
    default: null
  }
})

const emit = defineEmits(['closeModal', 'created'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const formData = ref({
  description: '',
  amount: null,
  service_category_id: null,
})


const resetForm = () => {
  formData.value = {
    description: '',
    amount: null,
    service_category_id: null,
  }
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
const categoryOptions = ref([])
const createCategoryDialog = ref(false)

const fetchCategories = async () => {
  try {
    const response = await serviceCategoryStore.getServiceCategories()
    if (response?.code === 200) {
      categoryOptions.value = (response.data || []).map(c => ({
        label: c.name,
        value: c.id
      }))
    }
  } catch {
    categoryOptions.value = []
  }
}

const submit = async () => {
  const description = formData.value.description?.trim()
  if (!description) {
    showNotify('warning', 'Ingresa el nombre del gasto')
    return
  }
  if (!formData.value.amount || formData.value.amount <= 0) {
    showNotify('warning', 'Ingresa un monto mensual válido')
    return
  }
  if (!formData.value.service_category_id) {
    showNotify('warning', 'Selecciona una categoría')
    return
  }

  loading.value = true
  try {
    const payload = {
      description,
      monthly_amount: formData.value.amount,
      service_category_id: formData.value.service_category_id,
      expense_type: 1,
    }
    if (props.budgetId) {
      payload.annual_budget_id = props.budgetId
    }
    const response = await ApiService.post('/api/annual-budgets/store-template', payload)
    if (response.data.code !== 201) throw response.data
    emit('created', response.data.data)
    showNotify('positive', 'Gasto creado')
    close()
  } catch (e) {
    showNotify('negative', e?.response?.data?.error || 'Error al crear gasto')
  } finally {
    loading.value = false
  }
}

watch(() => props.dialog, (open) => {
  dialogVisible.value = open
  if (open) {
    resetForm()
    fetchCategories()
  }
})

watch(dialogVisible, (open) => {
  if (!open && props.dialog) close()
})
const onServiceCategoryCreated = (created) => {
  if (!created?.id) return
  const exists = categoryOptions.value.some((o) => o.value === created.id)
  if (!exists) {
    categoryOptions.value = [
      ...categoryOptions.value,
      { label: created.name, value: created.id }
    ].sort((a, b) => a.label.localeCompare(b.label, 'es'))
  }
  formData.value.service_category_id = created.id
}
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close" persistent>
    <q-card style="min-width: min(360px, 92vw);" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Crear gasto fijo mensual</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Define el nombre, monto y categoría del gasto recurrente
      </div>

      <div class="text-subtitle2 text-black">Nombre del gasto</div>
      <q-input
        v-model="formData.description"
        dense
        borderless
        clearable
        class="form__inputsR mt-1"
        color="primary"
        placeholder="Ej: Agua, Luz, Internet..."
      />

      <div class="text-subtitle2 text-black q-mt-md">Monto mensual</div>
      <q-input
        v-model.number="formData.amount"
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
        type="number"
        placeholder="0.00"
        input-style="text-align: right;"
      />

      <div class="row items-end q-mt-md q-col-gutter-sm">
        <div class="col">
          <div class="text-subtitle2 text-black">Categoría de servicio</div>
          <q-select
            v-model="formData.service_category_id"
            :options="categoryOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
            color="primary"
          />
        </div>
        <div class="col-auto">
          <q-btn flat dense round color="primary" @click="createCategoryDialog = true">
            <q-icon name="eva-plus-outline" />
            <q-tooltip>Nueva categoría</q-tooltip>
          </q-btn>
        </div>
      </div>

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" no-caps @click="close" />
        <q-btn color="primary" label="Crear" no-caps :loading="loading" @click="submit" />
      </div>
    </q-card>
    <createServiceCategoryModal
      :dialog="createCategoryDialog"
      @close-modal="createCategoryDialog = false"
      @created="onServiceCategoryCreated"
    />
  </q-dialog>
</template>
