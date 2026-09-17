<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'

const serviceCategoryStore = useServiceCategoryStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
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

const categoryOptions = ref([])

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

const submit = () => {
  const description = formData.value.description?.trim()
  if (!description) {
    showNotify('warning', 'Ingresa el nombre del gasto')
    return
  }
  if (!formData.value.amount || formData.value.amount <= 0) {
    showNotify('warning', 'Ingresa un monto válido')
    return
  }
  if (!formData.value.service_category_id) {
    showNotify('warning', 'Selecciona una categoría')
    return
  }

  loading.value = true
  emit('created', {
    description,
    amount: formData.value.amount,
    expense_type: 1,
    service_category_id: formData.value.service_category_id,
    sort_order: 0,
  })
  showNotify('positive', 'Gasto creado')
  close()
  loading.value = false
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
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close" persistent>
    <q-card style="min-width: min(360px, 92vw);" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Crear gasto template</div>
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

      <div class="text-subtitle2 text-black q-mt-md">Monto</div>
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

      <div class="text-subtitle2 text-black q-mt-md">Categoría</div>
      <q-select
        v-model="formData.service_category_id"
        :options="categoryOptions"
        emit-value
        map-options
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
        placeholder="Seleccionar categoría"
      />

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" no-caps @click="close" />
        <q-btn color="primary" label="Crear" no-caps :loading="loading" @click="submit" />
      </div>
    </q-card>
  </q-dialog>
</template>
