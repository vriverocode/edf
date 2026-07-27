<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import AccountFormFields from '@/components/bankAccount/accountFormFields.vue'

const router = useRouter()
const route = useRoute()
const store = useBankAccountStore()
const loading = ref(false)
const fetching = ref(true)

const form = ref({
  type: 'bank',
  entity: '',
  account_number: '',
  cci: '',
  holder_name: '',
  yape_phone: '',
  yape_name: '',
})

onMounted(async () => {
  try {
    const res = await store.getAccountById(route.params.id)
    if (res.code !== 200) throw res
    const acct = res.data
    form.value = {
      type: acct.type || 'bank',
      entity: acct.entity || '',
      account_number: acct.account_number || '',
      cci: acct.cci || '',
      holder_name: acct.holder_name || '',
      yape_phone: acct.yape_phone || '',
      yape_name: acct.yape_name || '',
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: 'Error al cargar cuenta', timeout: 2000 })
    router.go(-1)
  } finally {
    fetching.value = false
  }
})

const onSubmit = async () => {
  loading.value = true
  try {
    const res = await store.updateAccount(route.params.id, form.value)
    if (res.code !== 200) throw res
    Notify.create({ color: 'positive', message: 'Cuenta actualizada con éxito', timeout: 2000 })
    router.go(-1)
  } catch (e) {
    Notify.create({ color: 'negative', message: e?.error || 'Error al actualizar cuenta', timeout: 2000 })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-page class="q-pa-md">
    <q-card class="q-mx-auto" style="max-width: 600px">
      <q-card-section>
        <div class="text-h6">Editar cuenta bancaria</div>
      </q-card-section>
      <q-card-section v-if="fetching" class="flex flex-center">
        <q-spinner color="primary" size="40px" />
      </q-card-section>
      <template v-else>
        <q-card-section>
          <AccountFormFields v-model="form" />
        </q-card-section>
        <q-card-actions align="around" class="q-pa-md">
          <q-btn label="Cancelar" color="primary" outline @click="router.go(-1)" />
          <q-btn label="Guardar" color="primary" :loading="loading" @click="onSubmit" />
        </q-card-actions>
      </template>
    </q-card>
  </q-page>
</template>
