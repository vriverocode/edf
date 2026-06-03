<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'

const serviceCategoryStore = useServiceCategoryStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['closeModal', 'created'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const formData = ref({
  name: '',
})

const resetForm = () => {
  formData.value = {
    name: '',
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

  loading.value = true
  serviceCategoryStore
    .createServiceCategory({
      name,
      status: 1,
    })
    .then((response) => {
      showNotify('positive', 'Categoría de servicio creada.')
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
      <div class="text-h6 q-mb-sm">Nueva categoría de servicio</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Registra una categoría para clasificar proveedores y servicios.
      </div>

      <div class="text-subtitle2 text-black">Nombre de la categoría</div>
      <q-input
        v-model="formData.name"
        dense
        borderless
        clearable
        class="form__inputsR mt-1"
        color="primary"
        autofocus
        @keyup.enter="submit"
      />

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" no-caps @click="close" />
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
</style>
