<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import { useFinancialAccountStore } from '@/services/store/financialAccount.store'

const router = useRouter()
const financialAccountStore = useFinancialAccountStore()
const loading = ref(false)
const currencies = ref([])

const typeOptions = [
  { label: 'Ingreso', value: 1 },
  { label: 'Egreso', value: 2 },
  { label: 'Mixta', value: 3 }
]

const statusOptions = [
  { label: 'Activo', value: 1 },
  { label: 'Inactivo', value: 0 }
]

const formData = ref({
  name: '',
  currency_id: null,
  initial_balance: null,
  current_balance: null,
  type: 1,
  status: 1
})

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const loadCurrencies = () => {
  financialAccountStore.getCurrencies()
    .then((response) => {
      currencies.value = response.data || []
    })
    .catch((error) => {
      showNotify('negative', typeof error === 'string' ? error : 'Error al cargar monedas')
    })
}

const createAccount = () => {
  loading.value = true
  const payload = { ...formData.value }
  if (payload.current_balance === null || payload.current_balance === '') {
    payload.current_balance = payload.initial_balance
  }

  financialAccountStore.createFinancialAccount(payload)
    .then(() => {
      showNotify('positive', 'Cuenta financiera creada con éxito')
      setTimeout(() => {
        router.go(-1)
      }, 700)
    })
    .catch((error) => {
      showNotify('negative', typeof error === 'string' ? error : 'Error al crear cuenta financiera')
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(() => {
  loadCurrencies()
})
</script>

<template>
  <div class="md:px-20 px-2  pb-8">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Nueva cuenta financiera
    </div>

    <q-form @submit="createAccount">
      <div class="row">
        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Nombre</div>
          <q-input v-model="formData.name" dense borderless class="form__inputsR mt-1"
            :rules="[val => !!val || 'El nombre es obligatorio']" />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Moneda</div>
          <q-select v-model="formData.currency_id" :options="currencies" option-label="name" option-value="id"
            emit-value map-options dense borderless class="form__inputsR mt-1"
            :rules="[val => !!val || 'La moneda es obligatoria']" />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Saldo inicial</div>
          <q-input v-model.number="formData.initial_balance" type="number" dense borderless class="form__inputsR mt-1"
            :rules="[val => val !== null && val !== '' || 'Saldo inicial requerido']" />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Saldo actual (opcional)</div>
          <q-input v-model.number="formData.current_balance" type="number" dense borderless
            class="form__inputsR mt-1" />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Tipo</div>
          <q-select v-model="formData.type" :options="typeOptions" option-label="label" option-value="value" emit-value
            map-options dense borderless class="form__inputsR mt-1" />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Estado</div>
          <q-select v-model="formData.status" :options="statusOptions" option-label="label" option-value="value"
            emit-value map-options dense borderless class="form__inputsR mt-1" />
        </div>
      </div>

      <div class="col-12 my-4 px-2 flex items-center justify-between">
        <q-btn outline="" color="grey-9" class="q-mr-sm" @click="router.go(-1)">
          Volver
        </q-btn>
        <q-btn color="primary" style="border-radius: 0.5rem" type="submit" :loading="loading">
          <div class="px-10 py-1">
            Guardar
          </div>
        </q-btn>
      </div>
    </q-form>
  </div>
</template>

<style lang="scss">
.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0px 1rem;
    }
  }
}
</style>
