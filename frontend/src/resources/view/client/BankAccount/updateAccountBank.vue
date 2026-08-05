<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import { useAuthStore } from '@/services/store/auth.services'
import AccountFormFields from '@/components/bankAccount/accountFormFields.vue'

const router = useRouter()
const route = useRoute()
const $q = useQuasar()
const store = useBankAccountStore()
const authStore = useAuthStore()
const loading = ref(false)
const fetching = ref(true)

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

onMounted(async () => {
  try {
    const res = await store.getAccountById(route.params.id)
    if (res.code !== 200) throw res
    const acct = res.data
    acct.data = JSON.parse(acct.data)
    form.value = {
      name: acct.name || '',
      data: {
        type: acct.data?.type || 'bank',
        entity: acct.data?.entity || '',
        account_number: acct.data?.account_number || '',
        cci: acct.data?.cci || '',
        holder_name: acct.data?.holder_name || '',
        yape_phone: acct.data?.yape_phone || '',
        yape_name: acct.data?.yape_name || '',
      },
    }
  } catch (e) {
    $q.notify({ type: 'negative', message: 'Error al cargar cuenta' })
    router.go(-1)
  } finally {
    fetching.value = false
  }
})

const onSubmit = () => {
  loading.value = true

  const payload = {
    name: form.value.name,
    data: JSON.stringify(form.value.data),
  }

  store
    .updateAccount(route.params.id, payload)
    .then(async (res) => {
      if (res.code !== 200) throw res
      await authStore.currentUser()
      $q.notify({ type: 'positive', message: 'Cuenta actualizada con éxito' })
      router.go(-1)
    })
    .catch((e) => {
      $q.notify({ type: 'negative', message: e?.error || 'Error al actualizar cuenta' })
    })
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="md:px-20 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Editar Cuenta Bancaria
    </div>

    <div v-if="fetching" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>

      <q-form v-else @submit="onSubmit">
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
