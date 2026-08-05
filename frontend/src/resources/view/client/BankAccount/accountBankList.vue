<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import { useAuthStore } from '@/services/store/auth.services'
import { useQuasar } from 'quasar'

const router = useRouter()
const $q = useQuasar()
const bankStore = useBankAccountStore()
const authStore = useAuthStore()

const accounts = ref([])
const loading = ref(false)

const formatDataAccounts = (data) => {
  return data.map((account) => {
    account.data = JSON.parse(account.data)
    return account
  })
}

const fetchAccounts = () => {
  loading.value = true
  bankStore
    .getAccounts()
    .then((response) => {
      if (response.code !== 200) throw response
      accounts.value = formatDataAccounts(response.data)
      console.log(accounts.value)
    })
    .catch(() => {
      accounts.value = []
    })
    .finally(() => {
      loading.value = false
    })
}

const goTo = (url) => {
  router.push(url)
}

const confirmDelete = (item) => {
  const d = item.data || {}
  const label = d.type === 'yape' ? 'el Yape' : 'la cuenta bancaria'
  const name = d.holder_name || d.yape_name || item.name
  $q.dialog({
    title: 'Eliminar cuenta',
    message: `¿Estás seguro de eliminar ${label} de ${name}?`,
    cancel: { label: 'Cancelar', flat: true, color: 'grey-7' },
    ok: { label: 'Eliminar', color: 'negative', unelevated: true },
    persistent: true,
  }).onOk(() => {
    bankStore
      .deleteAccount(item.id)
      .then(async () => {
        await authStore.currentUser()
        $q.notify({ type: 'positive', message: 'Cuenta eliminada correctamente' })
        fetchAccounts()
      })
      .catch((error) => {
        $q.notify({ type: 'negative', message: error || 'Error al eliminar cuenta' })
      })
  })
}

onMounted(() => {
  fetchAccounts()
})
</script>

<template>
  <div class="md:px-20 px-2">
    <div class="flex justify-end mb-4 px-2 md:px-12">
      <q-btn
        color="primary"
        icon="eva-plus-outline"
        label="Agregar cuenta"
        style="border-radius: 0.5rem;"
        no-caps
        @click="goTo('/client/account-bank/add')"
      />
    </div>

    <div v-if="loading" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>

    <div v-else-if="accounts.length === 0" class="flex flex-col items-center justify-center py-16 px-4">
      <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-4">
        <q-icon name="eva-credit-card-outline" size="2.5rem" color="blue-500" />
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cuentas agregadas</h3>
      <p class="text-gray-600 text-center mb-6">
        Agrega tus cuentas bancarias o Yape para recibir devoluciones
      </p>
    </div>

    <div v-else class="row w-full px-2 md:px-12">
      <div
        v-for="account in accounts"
        :key="account.id"
        class="col-12 my-2"
      >
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
          <div class="px-4 py-4">
            <div class="flex justify-between items-start mb-2">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <q-icon
                    :name="account.data?.type === 'yape' ? 'eva-credit-card-outline' : 'eva-credit-card-outline'"
                    color="primary"
                    size="1.3rem"
                  />
                  <span class="text-sm font-bold text-gray-900">
                    {{ account.name }}
                  </span>
                </div>
                <div class="text-sm text-gray-600">
                  <template v-if="account.data?.type === 'bank'">
                    <div>{{ account.data.holder_name }}</div>
                    <div class="text-sm mt-1">Cuenta: {{ account.data.account_number }}</div>
                    <div v-if="account.data.cci" class="text-sm mt-1">CCI: {{ account.data.cci }}</div>
                  </template>
                  <template v-else-if="account.data?.type === 'yape'">
                    <div>{{ account.data.yape_name }}</div>
                    <div class="text-sm mt-1">{{ account.data.yape_phone }}</div>
                  </template>
                  <div v-if="!account.data?.type" class="text-gray-400 italic">Sin datos configurados</div>
                </div>
              </div>
              <q-btn
                flat
                round
                color="grey-7"
                icon="eva-more-vertical-outline"
                size="sm"
              >
                <q-menu>
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup @click="goTo('/client/account-bank/update/' + account.id)">
                      <q-item-section>Editar</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="confirmDelete(account)">
                      <q-item-section class="text-negative">Eliminar</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
