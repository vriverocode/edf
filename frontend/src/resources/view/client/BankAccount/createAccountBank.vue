<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import { useAuthStore } from '@/services/store/auth.services'
import AccountFormFields from '@/components/bankAccount/accountFormFields.vue'

const router = useRouter()
const $q = useQuasar()
const store = useBankAccountStore()
const authStore = useAuthStore()
const loading = ref(false)

const form = ref({
  name: '',
  data: {
    type: 'bank',
    entity: '',
    account_number: '',
    cci: '',
    holder_name: '',
    yape_phone: '',
    yape_name: '',
  },
})

const onSubmit = () => {
  loading.value = true

  const payload = {
    name: form.value.name,
    data: JSON.stringify(form.value.data),
  }

  store
    .createAccount(payload)
    .then(async (res) => {
      if (res.code !== 200) throw res
      await authStore.currentUser()
      $q.notify({ type: 'positive', message: 'Cuenta agregada con éxito' })
      router.go(-1)
    })
    .catch((e) => {
      $q.notify({ type: 'negative', message: e?.error || 'Error al crear cuenta' })
    })
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="md:px-20 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Nueva Cuenta Bancaria
    </div>

    <q-form @submit="onSubmit">
      <div class="row w-full">
        <AccountFormFields v-model="form" />

        <div class="col-12 pb-8 mt-6 px-2 md:px-12 flex items-center justify-between">
          <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
            <q-btn color="grey-9" style="border-radius: 0.5rem;" @click="router.go(-1)">
              <div class="px-8 py-1">
                Volver
              </div>
            </q-btn>
          </div>
          <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
            <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="px-8 py-1">
                Guardar
              </div>
            </q-btn>
          </div>
        </div>
      </div>
    </q-form>
  </div>
</template>
