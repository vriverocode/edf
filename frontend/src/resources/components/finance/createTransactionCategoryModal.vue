<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useTransactionCategoryStore } from '@/services/store/transactionCategory.store'

const transactionCategoryStore = useTransactionCategoryStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false,
  },
  defaultType: {
    type: Number,
    default: 1,
  },
})

const emit = defineEmits(['closeModal', 'created'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const formData = ref({
  name: '',
  type: 1,
})

const typeOptions = [
  { label: 'Ingreso', value: 1 },
  { label: 'Egreso', value: 2 },
]

const resetForm = () => {
  formData.value = {
    name: '',
    type: props.defaultType,
  }
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2200,
  })
}

const close = () => {
  emit('closeModal')
}

const submit = () => {
  const name = formData.value.name?.trim()
  if (!name) {
    showNotify('warning', 'Ingresa el nombre de la categoría.')
    return
  }
  if (!formData.value.type) {
    showNotify('warning', 'Selecciona el tipo de categoría.')
    return
  }

  loading.value = true
  transactionCategoryStore
    .createTransactionCategory({
      name,
      type: formData.value.type,
      status: 1,
    })
    .then((response) => {
      showNotify('positive', 'Categoría contable creada.')
      emit('created', response.data)
      close()
    })
    .catch((err) => {
      const msg = typeof err === 'string' ? err : 'No se pudo crear la categoría.'
      showNotify('negative', msg)
    })
    .finally(() => {
      loading.value = false
    })
}

watch(
  () => props.dialog,
  (open) => {
    dialogVisible.value = open
    if (open) {
      resetForm()
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
    <q-card style="min-width: min(360px, 92vw);" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Nueva categoría contable</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Registra una categoría para clasificar movimientos contables.
      </div>

      <div class="text-subtitle2 text-black">Nombre de la categoría</div>
      <q-input v-model="formData.name" dense borderless clearable class="form__inputsR mt-1" color="primary"
        autofocus @keyup.enter="submit" />

      <div class="text-subtitle2 text-black q-mt-md">Tipo de categoría</div>
      <q-select v-model="formData.type" :options="typeOptions" option-label="label" option-value="value" emit-value
        map-options dense borderless class="form__inputsR mt-1" color="primary" />

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" @click="close"  no-caps/>
        <q-btn color="primary" label="Crear" no-caps :loading="loading" @click="submit" />
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

@media (max-width: 780px) {
  .form__inputsR {
    & :deep(.q-field__inner) {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
