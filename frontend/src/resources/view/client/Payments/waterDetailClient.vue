<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import { useAuthStore } from '@/services/store/auth.services'
import { storeToRefs } from 'pinia'
import moment from 'moment'

const route = useRoute()

const quotaStore = useQuotaStore()
const { currencySymbol } = storeToRefs(useAuthStore())
const urlMedia = import.meta.env.VITE_LARAVEL_API_URL

const detail = ref(null)
const loading = ref(false)
const error = ref(null)
const imageBroken = ref(false)

const quotaId = computed(() => route.params.id || route.query.id)
const prefix = computed(() => currencySymbol.value || 'S/')

const monthLabel = (month) => {
  const months = {
    1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril', 5: 'Mayo', 6: 'Junio',
    7: 'Julio', 8: 'Agosto', 9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
  }
  return months[month] || `Mes ${month}`
}
const photoUrl = computed(() => {
  const r = detail.value
  if (!r) return ''
  return urlMedia + (r.photo || r.meter_photo || r.proof_photo || '')
})
const loadDetail = async () => {
  try {
    loading.value = true
    error.value = null
    imageBroken.value = false
    const response = await quotaStore.getClientWaterDetailByQuotaId(quotaId.value)
    detail.value = response.data
  } catch (err) {
    error.value = err || 'Error al cargar el detalle de agua'
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
        <p class="text-gray-600 font-medium mt-3">Cargando detalle de agua...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <p class="text-gray-600 text-center mb-5">{{ error }}</p>
        <q-btn color="primary" outline @click="loadDetail">Reintentar</q-btn>
      </div>

      <div v-else-if="detail" class="bg-white rounded-xl shadow-lg border border-gray-100 p-5">
        <div class="text-2xl font-bold text-gray-900">
          Medicion de agua - {{ monthLabel(detail.month) }} {{ detail.year }}
        </div>
        <div class="text-grey-7 mt-1">
          Dpto: {{ detail.departament?.number || '-' }}
        </div>
        <div class="text-grey-7 mt-1">
          Registrado: {{ detail.created_at ? moment(detail.created_at).format('DD/MM/YYYY HH:mm') : '-' }}
        </div>

        <div class="mt-5 space-y-3">
          <div class="detail-row">
            <span>Lectura anterior</span>
            <span>{{ detail.previous_reading ?? '-' }}</span>
          </div>
          <div class="detail-row">
            <span>Lectura actual</span>
            <span>{{ detail.current_reading ?? '-' }}</span>
          </div>
          <div class="detail-row">
            <span>Consumo (m3)</span>
            <span>{{ detail.water_consumption_m3 ?? '-' }}</span>
          </div>
          <div class="detail-row">
            <span>Precio por m3</span>
            <span>{{ prefix }} {{ detail.water_price_per_m3 ?? '-' }}</span>
          </div>
          <div class="detail-row">
            <span>Total agua</span>
            <span>{{ prefix }} {{ detail.water_amount ?? '-' }}</span>
          </div>
        </div>

        <div class="mt-6">
          <div class="text-gray-600 font-medium mb-2">Foto del medidor</div>
          <div class="rounded-lg overflow-hidden border border-gray-200 bg-grey-2 flex flex-col items-center justify-center"
            style="min-height: 220px;">
            <template v-if="detail.photo && !imageBroken">
              <q-img :src="photoUrl" fit="contain" style="max-height: 360px;" spinner-color="primary"
                @error="imageBroken = true" />
            </template>
            <template v-else>
              <q-icon name="eva-image-outline" size="3rem" color="grey-5" class="q-mt-md" />
              <p class="text-grey-6 text-center q-px-md q-pb-md q-mb-none">
                {{ detail.photo ? 'No se pudo cargar la imagen.' : 'No hay foto registrada.' }}
              </p>
            </template>
          </div>
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
