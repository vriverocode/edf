<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useFinancialAccountStore } from '@/services/store/financialAccount.store'

const route = useRoute()
const router = useRouter()
const financialAccountStore = useFinancialAccountStore()
const loading = ref(false)
const fetchingData = ref(true)
const currencies = ref([])
const accountId = route.params.id

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

const loadCurrencies = async () => {
  const response = await financialAccountStore.getCurrencies()
  currencies.value = response.data || []
}

const loadAccount = async () => {
  const response = await financialAccountStore.getFinancialAccountById(accountId)
  const data = response.data
  formData.value = {
    name: data.name,
    currency_id: data.currency_id,
    initial_balance: Number(data.initial_balance),
    current_balance: Number(data.current_balance),
    type: Number(data.type),
    status: Number(data.status)
  }
}

const updateAccount = () => {
  loading.value = true
  financialAccountStore.updateFinancialAccount(accountId, formData.value)
    .then(() => {
      showNotify('positive', 'Cuenta financiera actualizada con éxito')
      setTimeout(() => {
        router.push('/admin/financial-accounts')
      }, 700)
    })
    .catch((error) => {
      showNotify('negative', typeof error === 'string' ? error : 'Error al actualizar cuenta financiera')
    })
    .finally(() => {
      loading.value = false
    })
}

onMounted(async () => {
  try {
    await Promise.all([loadCurrencies(), loadAccount()])
  } catch (error) {
    showNotify('negative', typeof error === 'string' ? error : 'No se pudo cargar la cuenta')
    router.push('/admin/financial-accounts')
  } finally {
    fetchingData.value = false
  }
})
</script>

<template>
  <div class="md:px-24 px-2 h-full" style="overflow: hidden;">
    <div class="text-center text-black text-h5 text-bold md:mb-8 mb-2 headerForm">
      Editar cuenta financiera
    </div>

    <div v-if="fetchingData" class="flex flex-center" style="height: 93%;">
      <q-spinner color="primary" size="3em" />
    </div>

    <q-form v-else class="formContent" style="overflow: auto;" @submit="updateAccount">
      <div class="row">
        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Nombre</div>
          <q-input
            v-model="formData.name"
            dense
            borderless
            class="form__inputsR mt-1"
            :rules="[val => !!val || 'El nombre es obligatorio']"
          />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Moneda</div>
          <q-select
            v-model="formData.currency_id"
            :options="currencies"
            option-label="name"
            option-value="id"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
            :rules="[val => !!val || 'La moneda es obligatoria']"
          />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Saldo inicial</div>
          <q-input
            v-model.number="formData.initial_balance"
            type="number"
            dense
            borderless
            class="form__inputsR mt-1"
            :rules="[val => val !== null && val !== '' || 'Saldo inicial requerido']"
          />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Saldo actual</div>
          <q-input
            v-model.number="formData.current_balance"
            type="number"
            dense
            borderless
            class="form__inputsR mt-1"
            :rules="[val => val !== null && val !== '' || 'Saldo actual requerido']"
          />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Tipo</div>
          <q-select
            v-model="formData.type"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
          />
        </div>

        <div class="col-md-6 col-12 px-2 mb-3">
          <div class="text-subtitle2 text-black">Estado</div>
          <q-select
            v-model="formData.status"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
          />
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
.headerForm {
  height: 7%;
}

.formContent {
  height: 93%;
}

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 10px 1rem;
  }
}
</style>

