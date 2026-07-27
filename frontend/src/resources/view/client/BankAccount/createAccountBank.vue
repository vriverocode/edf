<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import AccountFormFields from '@/components/bankAccount/accountFormFields.vue'

const router = useRouter()
const store = useBankAccountStore()
const loading = ref(false)

const form = ref({
  type: 'bank',
  entity: '',
  account_number: '',
  cci: '',
  holder_name: '',
  yape_phone: '',
  yape_name: '',
})

const onSubmit = async () => {
  loading.value = true
  try {
    const res = await store.createAccount(form.value)
    if (res.code !== 200) throw res
    Notify.create({ color: 'positive', message: 'Cuenta agregada con éxito', timeout: 2000 })
    router.go(-1)
  } catch (e) {
    Notify.create({ color: 'negative', message: e?.error || 'Error al crear cuenta', timeout: 2000 })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page class="q-pa-md">
    <q-card class="q-mx-auto" style="max-width: 600px">
      <q-card-section>
        <div class="text-h6">Nueva cuenta bancaria</div>
      </q-card-section>
      <q-card-section>
        <AccountFormFields v-model="form" />
      </q-card-section>
      <q-card-actions align="around" class="q-pa-md">
        <q-btn label="Cancelar" color="primary" outline @click="router.go(-1)" />
        <q-btn label="Guardar" color="primary" :loading="loading" @click="onSubmit" />
      </q-card-actions>
    </q-card>
  </q-page>
</template>
