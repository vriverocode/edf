<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useProviderStore } from '@/services/store/provider.store'
import createServiceCategoryModal from '@/components/finance/createServiceCategoryModal.vue'

const providerStore = useProviderStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  serviceCategories: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['closeModal', 'created', 'categoryCreated'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const formData = ref({
  name: '',
  tax_id: '',
  service_category_id: null,
  contact_name: '',
  phone: '',
  email: ''
})

const categoryOptions = ref([])
const createCategoryDialog = ref(false)

const syncCategoryOptions = (list) => {
  categoryOptions.value = (list || []).map((c) => ({
    label: c.name,
    value: c.id
  }))
}

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
  emit('categoryCreated', created)
}

const resetForm = () => {
  formData.value = {
    name: '',
    tax_id: '',
    service_category_id: null,
    contact_name: '',
    phone: '',
    email: ''
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

const submit = () => {
  const name = formData.value.name?.trim()
  const taxId = formData.value.tax_id?.trim()

  if (!name) {
    showNotify('warning', 'Ingresa el nombre del proveedor.')
    return
  }
  if (!taxId) {
    showNotify('warning', 'Ingresa el RUC o documento tributario.')
    return
  }
  if (!formData.value.service_category_id) {
    showNotify('warning', 'Selecciona la categoría de servicio.')
    return
  }

  loading.value = true
  providerStore
    .createProvider({
      name,
      tax_id: taxId,
      service_category_id: formData.value.service_category_id,
      contact_name: formData.value.contact_name?.trim() || null,
      phone: formData.value.phone?.trim() || null,
      email: formData.value.email?.trim() || null
    })
    .then((response) => {
      showNotify('positive', 'Proveedor registrado.')
      emit('created', response.data)
      close()
    })
    .catch((err) => {
      const msg = typeof err === 'string' ? err : 'No se pudo registrar el proveedor.'
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
      syncCategoryOptions(props.serviceCategories)
    }
  }
)

watch(
  () => props.serviceCategories,
  (list) => {
    syncCategoryOptions(list)
  },
  { deep: true }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close">
    <q-card style="min-width: min(400px, 92vw);" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Nuevo proveedor</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Registra un proveedor para asociarlo al gasto.
      </div>

      <div class="text-subtitle2 text-black">Nombre o razón social</div>
      <q-input
        v-model="formData.name"
        dense
        borderless
        clearable
        class="form__inputsR mt-1"
        color="primary"
        autofocus
      />

      <div class="text-subtitle2 text-black q-mt-md">RUC / documento tributario</div>
      <q-input
        v-model="formData.tax_id"
        dense
        borderless
        clearable
        class="form__inputsR mt-1"
        color="primary"
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

      <div class="text-subtitle2 text-black q-mt-md">Contacto (opcional)</div>
      <q-input
        v-model="formData.contact_name"
        dense
        borderless
        clearable
        class="form__inputsR mt-1"
        color="primary"
      />

      <div class="row q-col-gutter-sm q-mt-sm">
        <div class="col-6">
          <div class="text-subtitle2 text-black">Teléfono</div>
          <q-input v-model="formData.phone" dense borderless clearable class="form__inputsR mt-1" color="primary" />
        </div>
        <div class="col-6">
          <div class="text-subtitle2 text-black">Correo</div>
          <q-input v-model="formData.email" dense borderless clearable class="form__inputsR mt-1" color="primary" />
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
