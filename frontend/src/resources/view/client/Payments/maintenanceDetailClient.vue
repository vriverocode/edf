<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import { useAuthStore } from '@/services/store/auth.services'
import { storeToRefs } from 'pinia'

const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()
const { currencySymbol } = storeToRefs(useAuthStore())

const detail = ref(null)
const loading = ref(false)
const error = ref(null)

const quotaId = computed(() => route.params.id || route.query.id)
const prefix = computed(() => currencySymbol.value || 'S/')

const monthLabel = (month) => {
  const months = {
    1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril', 5: 'Mayo', 6: 'Junio',
    7: 'Julio', 8: 'Agosto', 9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
  }
  return months[month] || `Mes ${month}`
}

const loadDetail = async () => {
  try {
    loading.value = true
    error.value = null
    const response = await quotaStore.getClientMaintenanceDetailByQuotaId(quotaId.value)
    detail.value = response.data
  } catch (err) {
    error.value = err || 'Error al cargar el detalle de mantenimiento'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!quotaId.value) {
    error.value = 'No se encontro la cuota'
    return
  }
  loadDetail()
})
</script>

<template>
  <div class="h-full w-full bg-white overflow-auto">
    <div class="md:px-24 md:py-8 px-4 py-5">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-3">Cargando detalle de mantenimiento...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <p class="text-gray-600 text-center mb-5">{{ error }}</p>
        <q-btn color="primary" outline @click="loadDetail">Reintentar</q-btn>
      </div>

      <div v-else-if="detail" class="bg-white rounded-xl shadow-lg border border-gray-100 p-5">
        <div class="text-2xl font-bold text-gray-900">
          Mantenimiento - {{ monthLabel(detail.month) }} {{ detail.year }}
        </div>
        <div class="text-grey-7 mt-1">
          Dpto: {{ detail.departament?.number || '-' }}
        </div>

        <div class="mt-5 space-y-3">
          <div class="detail-row">
            <span>Nivel de participacion</span>
            <span>{{ detail.maintenance_participation_percentage ?? '-' }}%</span>
          </div>
          <div class="detail-row">
            <span>Presupuesto de mantenimiento</span>
            <span>{{ prefix }} {{ detail.maintenance_budget_total ?? '-' }}</span>
          </div>
          <div class="detail-row">
            <span>Tu monto de mantenimiento</span>
            <span>{{ prefix }} {{ detail.maintenance_amount ?? '-' }}</span>
          </div>
        </div>

        <div class="mt-6">
          <q-btn color="primary" outline @click="router.back()">Volver</q-btn>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid rgba(211, 211, 211, 0.534);
  color: #374151;
  font-weight: 600;
}
</style>
