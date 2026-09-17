<script setup>
import { ref, onMounted } from 'vue'
import { usePayStore } from '@/services/store/pay.store'
import { useRouter, useRoute } from 'vue-router'

const credits = ref([])
const totalBalance = ref(0)
const loading = ref(true)
const payStore = usePayStore()
const router = useRouter()
const route = useRoute()
const search = ref('')

const getCredits = () => {
  loading.value = true
  payStore.getAllCredits(search.value)
    .then((response) => {
      console.log(response)
      credits.value = response.data || []
      totalBalance.value = response.total || 0
    })
    .catch(() => {})
    .finally(() => {
      loading.value = false
    })
}

const applySearch = () => {
  getCredits()
}

const goToDetail = (id) => {
  router.push(`/admin/credits/${id}`)
}

onMounted(() => {
  getCredits()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <div class="px-4 pt-4 md:px-36">
        <!-- Saldo total -->
        <div class="bg-primary text-white rounded-xl p-4 mb-4">
          <div class="text-sm opacity-80 mb-1">Saldo total a favor</div>
          <div class="text-2xl font-bold">S/. {{ totalBalance.toFixed(2) }}</div>
          <div class="text-xs opacity-70 mt-1">{{ credits.length }} departamento{{ credits.length !== 1 ? 's' : '' }} con saldo</div>
        </div>

        <!-- Buscador -->
        <q-input dense outlined v-model="search" placeholder="Buscar por nombre o departamento..."
          @keyup.enter="applySearch" clearable @clear="applySearch" color="teal">
          <template v-slot:prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Lista -->
      <div v-else class="px-4 py-4 md:px-28">
        <div v-if="credits.length > 0" class="md:px-5">
          <div v-for="credit in credits" :key="credit.departament_id"
            class="bg-white rounded-xl shadow-md my-3 border border-gray-100 overflow-hidden cursor-pointer hover:shadow-lg transition-shadow"
            @click="goToDetail(credit.departament_id)">
            <div class="px-4 py-3 flex items-center justify-between">
              <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center bg-blue-50 text-blue-500">
                  <q-icon name="eva-home-outline" size="1.5rem" />
                </div>
                <div>
                  <div class="font-bold text-gray-900">{{ credit.number }}</div>
                  <div class="text-sm text-gray-500">{{ credit.owner_name }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="text-lg font-bold text-green-600">S/. {{ credit.balance.toFixed(2) }}</div>
                <div class="text-xs text-gray-400">Saldo activo</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-alert-circle-outline" class="text-blue-500" size="2.5rem" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay saldos a favor</h3>
          <p class="text-gray-600 text-center">Ningún departamento tiene saldo a favor activo.</p>
        </div>
      </div>
    </div>
  </div>
</template>
