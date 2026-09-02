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
const imageBroken = ref(false)

const getStatusColor = (status) => {
  switch (Number(status)) {
    case 0: return 'negative'
    case 1: return 'blue-7'
    case 2: return 'positive'
    case 3: return 'warning'
    default: return 'grey-7'
  }
}

const goBack = () => {
  router.push('/admin/maintenances')
}

const goToComplete = () => {
  router.push(`/admin/maintenances/${route.params.id}/complete`)
}

const goToEdit = () => {
  router.push(`/admin/maintenances/${route.params.id}/edit`)
}

const goToChangeStatus = () => {
  router.push(`/admin/maintenances/${route.params.id}/status`)
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

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20 text-center">
        <q-icon name="eva-alert-triangle-outline" size="3rem" color="negative" />
        <h3 class="text-lg font-semibold text-gray-900 mb-1 mt-3">{{ error }}</h3>
        <q-btn flat color="primary" label="Volver a mantenimientos" @click="goBack" />
      </div>

      <div v-else-if="maintenance" class="px-4 py-6 md:px-28">
  
        <div class="bg-white rounded-xl border border-gray-100 shadow-lg p-5 md:px-8">
          <div class="flex items-start justify-between flex-wrap gap-3">
            <h1 class="text-xl font-bold text-gray-900">{{ maintenance.title }}</h1>
            <q-chip :color="getStatusColor(maintenance.status)" text-color="white" class="text-bold px-3" dense>
              {{ maintenance.status_label }}
            </q-chip>
          </div>

          <div class="row mt-4">
            <div class="col-6 col-md-4 px-2">
              <q-btn
              v-if="Number(maintenance.status) !== 2"
              color="positive"
              unelevated
              no-caps
              class="w-full"
              icon="eva-checkmark-circle-2-outline"
              label="Completar"
              style="border-radius: 0.5rem;"
              @click="goToComplete"
            />
            </div>
            <div class="col-6 col-md-4 px-2">
              <q-btn
                color="primary"
                no-caps
                icon="eva-edit-outline"
                label="Editar"
                class="w-full"
                unelevated
                style="border-radius: 0.5rem;"
                @click="goToEdit"
              />
            </div>
            <div class="col-12 col-md-4 pt-4 md:pt-0 px-2">
              <q-btn
                color="primary"
                outline
                no-caps
                icon="eva-edit-2-outline"
                label="Cambiar estado"
                class="w-full"
                style="border-radius: 0.5rem;"
                @click="goToChangeStatus"
              />
            </div>
          </div>

          <div class="mt-4 space-y-3">
            <div v-if="maintenance.comun_area" class="row items-center">
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

            <div class="mt-4 pt-3 border-t border-gray-200">
              <h2 class="text-subtitle1 text-bold text-gray-800 q-mb-sm">Foto del mantenimiento</h2>
              <div
                class="rounded-lg overflow-hidden border border-gray-200 bg-grey-2 flex flex-col items-center justify-center"
                style="min-height: 200px; max-width: 22rem;">
                <template v-if="maintenance.photo && !imageBroken">
                  <q-img
                    :src="maintenance.photo"
                    fit="contain"
                    spinner-color="primary"
                    style="max-height: 360px; width: 100%;"
                    @error="imageBroken = true" />
                </template>
                <template v-else>
                  <q-icon name="eva-image-outline" size="3rem" color="grey-5" class="q-mt-md" />
                  <p class="text-grey-6 text-center q-px-md q-pb-md q-mb-none">
                    {{ maintenance.photo ? 'No se pudo cargar la imagen.' : 'No hay foto registrada.' }}
                  </p>
                </template>
              </div>
            </div>

            <div v-if="maintenance.evidence_photo" class="mt-4 pt-3 border-t border-gray-100">
              <h2 class="text-subtitle1 text-bold text-gray-800 q-mb-sm">Evidencia de finalización</h2>
              <q-img :src="maintenance.evidence_photo" spinner-color="primary" style="max-width: 22rem; border-radius: 0.75rem;" />
            </div>

            <div v-if="maintenance.completion_description" class="mt-4 pt-3 border-t border-gray-100">
              <h2 class="text-subtitle1 text-bold text-gray-800 q-mb-sm">Descripción de finalización</h2>
              <p class="text-body1 text-gray-700 whitespace-pre-line">{{ maintenance.completion_description }}</p>
            </div>

            <div v-if="maintenance.completed_at" class="row items-center">
              <q-icon name="eva-checkmark-circle-2-outline" size="1.1rem" color="positive" class="q-mr-sm" />
              <span class="text-body1 text-grey-7">
                Completado el {{ moment(maintenance.completed_at).format('DD/MM/YYYY HH:mm') }}
              </span>
            </div>

            <div class="mt-4 pt-3 border-t border-gray-100 text-caption text-grey-6">
              Registrado el {{ moment(maintenance.created_at).format('DD/MM/YYYY HH:mm') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
