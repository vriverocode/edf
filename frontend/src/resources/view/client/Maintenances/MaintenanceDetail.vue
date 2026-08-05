<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMaintenanceStore } from '@/services/store/maintenance.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const maintenanceStore = useMaintenanceStore()

const maintenance = ref(null)
const loading = ref(true)
const error = ref('')

const getStatusColor = (status) => {
  switch (Number(status)) {
    case 1: return 'blue-7'
    case 2: return 'warning'
    case 3: return 'positive'
    default: return 'negative'
  }
}

const goBack = () => {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/client/notices/list')
  }
}

onMounted(async () => {
  try {
    maintenance.value = await maintenanceStore.getMaintenance(route.params.id)
  } catch (e) {
    error.value = e || 'Error al cargar el mantenimiento'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>
      <div v-else-if="maintenance" class="px-4 pb-6 md:px-28">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 md:px-8">
          <div class="flex items-start justify-between flex-wrap gap-3">
            <h1 class="text-xl font-bold text-gray-900">{{ maintenance.title }}</h1>
            <q-chip :color="getStatusColor(maintenance.status)" text-color="white" class="text-bold px-3" dense>
              {{ maintenance.status_label }}
            </q-chip>
          </div>

          <div class="mt-4 space-y-3">
            <div v-if="maintenance.comun_area" class="row items-center">
              <q-icon name="eva-building-outline" size="1.1rem" color="teal-9" class="q-mr-sm" />
              <span class="text-bold text-teal-9">Área: {{ maintenance.comun_area.name }}</span>
              <q-chip dense color="teal-1" text-color="teal-9" size="sm" class="q-ml-2">
                Bloqueada
              </q-chip>
            </div>

            <div class="row items-center">
              <q-icon name="eva-calendar-outline" size="1.1rem" color="grey-7" class="q-mr-sm" />
              <span class="text-body1">{{ moment(maintenance.date).format('DD/MM/YYYY') }}</span>
            </div>

            <div class="row items-center">
              <q-icon name="eva-clock-outline" size="1.1rem" color="grey-7" class="q-mr-sm" />
              <span v-if="maintenance.time_from && maintenance.time_to" class="text-body1">
                {{ maintenance.time_from.substring(0, 5) }} - {{ maintenance.time_to.substring(0, 5) }}
              </span>
              <span v-else class="text-body1">Todo el día</span>
            </div>

            <div v-if="maintenance.description" class="mt-4 pt-3 border-t border-gray-100">
              <h2 class="text-subtitle1 text-bold text-gray-800 q-mb-sm">Motivo</h2>
              <p class="text-body1 text-gray-700 whitespace-pre-line">{{ maintenance.description }}</p>
            </div>

            <div v-if="maintenance.photo" class="mt-4 pt-3 border-t border-gray-100">
              <h2 class="text-subtitle1 text-bold text-gray-800 q-mb-sm">Evidencia</h2>
              <q-img :src="maintenance.photo" spinner-color="primary" style="max-width: 22rem; border-radius: 0.75rem;" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
