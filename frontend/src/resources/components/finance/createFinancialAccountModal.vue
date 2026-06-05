<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useFinancialAccountStore } from '@/services/store/financialAccount.store'

const financialAccountStore = useFinancialAccountStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['closeModal', 'created'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)
const currencies = ref([])
const formData = ref({
  name: '',
  currency_id: null,
  initial_balance: 0,
  type: 1,
})

const typeOptions = [
  { label: 'Banco', value: 1 },
  { label: 'Caja chica', value: 2 },
  { label: 'Otro', value: 3 },
]

const resetForm = () => {
  formData.value = {
    name: '',
    currency_id: null,
    initial_balance: 0,
    type: 1,
  }
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2200,
  })
}

const loadCurrencies = async () => {
  try {
    const res = await financialAccountStore.getCurrencies()
    currencies.value = (res.data || []).map((c) => ({
      label: `${c.name} (${c.symbol})`,
      value: c.id,
    }))
    if (currencies.value.length === 1) {
      formData.value.currency_id = currencies.value[0].value
    }
  } catch (e) {
    currencies.value = []
  }
}

const close = () => {
  emit('closeModal')
}

const submit = () => {
  const name = formData.value.name?.trim()
  if (!name) {
    showNotify('warning', 'Ingresa el nombre de la cuenta.')
    return
  }
  if (!formData.value.currency_id) {
    showNotify('warning', 'Selecciona la moneda.')
    return
  }

  loading.value = true
  financialAccountStore
    .createFinancialAccount({
      name,
      currency_id: formData.value.currency_id,
      initial_balance: formData.value.initial_balance || 0,
      type: formData.value.type,
      status: 1,
    })
    .then((response) => {
      showNotify('positive', 'Cuenta financiera creada.')
      emit('created', response.data)
      close()
    })
    .catch((err) => {
      const msg = typeof err === 'string' ? err : 'No se pudo crear la cuenta.'
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
      loadCurrencies()
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
      <div class="text-h6 q-mb-sm">Nueva cuenta financiera</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Registra una cuenta para asociar ingresos y egresos.
      </div>

      <div class="text-subtitle2 text-black">Nombre de la cuenta</div>
      <q-input v-model="formData.name" dense borderless clearable class="form__inputsR mt-1" color="primary"
        autofocus @keyup.enter="submit" />

      <div class="text-subtitle2 text-black q-mt-md">Moneda</div>
      <q-select v-model="formData.currency_id" :options="currencies" option-label="label" option-value="value"
        emit-value map-options dense borderless class="form__inputsR mt-1" color="primary" />

      <div class="text-subtitle2 text-black q-mt-md">Saldo inicial</div>
      <q-input v-model.number="formData.initial_balance" dense borderless class="form__inputsR mt-1" color="primary"
        type="number" min="0" />

      <div class="text-subtitle2 text-black q-mt-md">Tipo de cuenta</div>
      <q-select v-model="formData.type" :options="typeOptions" option-label="label" option-value="value" emit-value
        map-options dense borderless class="form__inputsR mt-1" color="primary" />

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" @click="close" no-caps />
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
