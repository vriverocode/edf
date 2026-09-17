<script setup>
import { ref, onMounted } from 'vue'
import { usePayStore } from '@/services/store/pay.store'
import { useRoute } from 'vue-router'
import moment from 'moment'

const route = useRoute()
const payStore = usePayStore()
const deptId = route.params.id

const balance = ref(0)
const transactions = ref([])
const loading = ref(true)
const loadingTransactions = ref(true)
const departament = ref({})

const getBalance = () => {
  payStore.getCreditBalance(deptId)
    .then((response) => {
      balance.value = response.data?.balance || 0
      departament.value = response.data?.departament || {}
    })
    .catch(() => {})
}

const getTransactions = () => {
  loadingTransactions.value = true
  payStore.getCreditTransactions(deptId)
    .then((response) => {
      transactions.value = response.data || []
    })
    .catch(() => {})
    .finally(() => {
      loadingTransactions.value = false
    })
}

const typeLabel = (type) => {
  const labels = { created: 'Crédito', applied: 'Aplicado', expired: 'Expirado' }
  return labels[type] || type
}

const typeColor = (type) => {
  const colors = { created: 'green', applied: 'orange', expired: 'red' }
  return colors[type] || 'grey'
}

const typeIcon = (type) => {
  const icons = { created: 'eva-arrow-upward-outline', applied: 'eva-arrow-downward-outline', expired: 'eva-close-circle-outline' }
  return icons[type] || 'eva-minus-outline'
}

onMounted(() => {
  Promise.all([getBalance(), getTransactions()]).finally(() => {
    loading.value = false
  })
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <template v-else>
        <!-- Saldo actual -->
        <div class="px-4 pt-4 md:px-36">
          <div class="bg-primary text-white rounded-xl p-5 mb-4 ">
            <div>
              <div class="text-sm opacity-80 mb-1">Departamento</div>
              <div class="text-xl font-bold">{{ departament?.number || '---' }}</div>
            </div>
            <div class="flex column items-end">
              <div class="text-sm opacity-80 mb-1 pt-3">Saldo actual</div>
              <div class="text-3xl font-bold">S/. {{ balance.toFixed(2) }}</div>
            </div>
          </div>
          
        </div>

        <!-- Historial -->
        <div class="px-4 md:px-28">
          <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3 px-1">Historial de transacciones</h3>

          <div v-if="loadingTransactions" class="flex justify-center py-10">
            <q-spinner-dots color="primary" size="3rem" />
          </div>

          <div v-else-if="transactions.length > 0" class="space-y-3 pb-6">
            <div v-for="tx in transactions" :key="tx.id"
              class="bg-white rounded-xl shadow-sm border border-gray-100 px-4 py-3">
              <div class="flex items-start justify-between">
                <div class="flex items-start space-x-3">
                  <div :class="'bg-' + typeColor(tx.type) + '-50 text-' + typeColor(tx.type) + '-500'"
                    class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                    <q-icon :name="typeIcon(tx.type)" size="1.2rem" />
                  </div>
                  <div>
                    <div class="font-semibold text-gray-900 text-sm">{{ typeLabel(tx.type) }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ tx.description }}</div>
                    <div class="text-xs text-gray-400 mt-1">
                      {{ moment(tx.created_at).format('DD/MM/YYYY HH:mm') }}
                    </div>
                    <div v-if="tx.pay" class="text-xs text-gray-400 mt-0.5">
                      Pago #{{ tx.pay.id }} — S/. {{ tx.pay.amount }}
                    </div>
                    <div v-if="tx.quota" class="text-xs text-gray-400 mt-0.5">
                      Cuota: {{ tx.quota.month_label }} {{ tx.quota.year }}
                    </div>
                  </div>
                </div>
                <div class="text-right w-full">
                  <div :class="tx.type === 'created' ? 'text-green-600' : 'text-red-500'"
                    class="font-bold text-sm">
                    {{ tx.type === 'created' ? '+' : '-' }} S/. {{ Math.abs(tx.amount).toFixed(2) }}
                  </div>
                  <div class="text-xs text-gray-400 mt-0.5">
                    Saldo: S/. {{ tx.balance_after.toFixed(2) }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="flex flex-col items-center justify-center py-16">
            <q-icon name="eva-inbox-outline" class="text-gray-300 mb-3" size="3rem" />
            <p class="text-gray-500 text-sm">No hay transacciones registradas</p>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
