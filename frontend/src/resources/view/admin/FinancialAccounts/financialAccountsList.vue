<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import { useFinancialAccountStore } from '@/services/store/financialAccount.store'
import iconsApp from '@/assets/icons/index'

const router = useRouter()
const financialAccountStore = useFinancialAccountStore()
const loading = ref(false)
const accounts = ref([])

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const loadAccounts = () => {
  loading.value = true
  financialAccountStore.getFinancialAccounts()
    .then((response) => {
      accounts.value = response.data || []
    })
    .catch((error) => {
      showNotify('negative', typeof error === 'string' ? error : 'Error al cargar cuentas financieras')
    })
    .finally(() => {
      loading.value = false
    })
}

const toggleStatus = (account) => {
  const nextStatus = Number(account.status) === 1 ? 0 : 1
  financialAccountStore.updateFinancialAccountStatus(account.id, { status: nextStatus })
    .then(() => {
      showNotify('positive', 'Estado actualizado')
      loadAccounts()
    })
    .catch((error) => {
      showNotify('negative', typeof error === 'string' ? error : 'No se pudo actualizar estado')
    })
}

const goTo = (url) => {
  router.push(url)
}

onMounted(() => {
  loadAccounts()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%;">
      <div style="height: 100%;" v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div style="height: 100%;" v-else>
        <div class="row px-5 md:px-28" style="height: 8%;">
          <div class="col-12 flex justify-end">
            <q-btn color="primary" unelevated class="w-full  md:mt-0 md:mx-5 createButton"
              style="border-radius: 0.5rem; height: max-content;" @click="goTo('/admin/financial-accounts/add')">
              <div class="flex items-center py-1">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">
                  Agregar cuenta
                </div>
              </div>
            </q-btn>
          </div>
        </div>
        <div class="px-4 pb-6 pt-3 md:px-28" style="height: 92%; overflow: auto;">
          <div v-if="accounts.length > 0" class="space-y-3 pt-3 md:px-5">
            <div v-for="account in accounts" :key="account.id"
              class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
              style="position: relative;">
              <div class="px-0 pb-4 pt-2 border-b border-dashed border-gray-300">
                <div class="flex justify-between items-start mb-2 px-4">
                  <div class="flex-1">
                    <h3 class="text-lg font-bold text-gray-900 mb-0">
                      {{ account.name }}
                    </h3>
                    <div class="text-caption text-grey-7 mt-1">
                      {{ account.currency?.name }} ({{ account.currency?.symbol }})
                    </div>
                  </div>
                  <span :class="account.status_color"
                    class="inline-block px-3 py-2 text-xs font-bold text-white badgeAccount">
                    {{ account.status_label }}
                  </span>
                </div>

                <div class="px-4 text-sm text-gray-700">
                  <div class="mb-1"><strong>Saldo inicial:</strong> {{ account.initial_balance }}</div>
                  <div class="mb-1"><strong>Saldo actual:</strong> {{ account.current_balance }}</div>
                  <div><strong>Tipo:</strong> {{ account.type_label }}</div>
                </div>
              </div>

              <div class="py-2 px-4 bg-gray-50">
                <div class="flex justify-end items-center">
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                    <div v-html="iconsApp.optionsBook"></div>
                    <q-menu>
                      <q-list style="min-width: 150px">
                        <q-item clickable v-close-popup @click="goTo('/admin/financial-accounts/update/' + account.id)">
                          <q-item-section>Editar</q-item-section>
                        </q-item>
                        <q-item clickable v-close-popup @click="toggleStatus(account)">
                          <q-item-section>{{ Number(account.status) === 1 ? 'Deshabilitar' : 'Habilitar'
                          }}</q-item-section>
                        </q-item>
                      </q-list>
                    </q-menu>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="flex flex-col items-center justify-center py-20">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
              <q-icon name="eva-credit-card-outline" color="primary" size="42px" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cuentas financieras registradas</h3>
            <p class="text-gray-600 text-center mb-6">Agrega una cuenta para comenzar a llevar control financiero.</p>
          </div>
        </div>
        
      </div>
    </div>
  </div>
</template>

<style scoped>
.badgeAccount {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>
