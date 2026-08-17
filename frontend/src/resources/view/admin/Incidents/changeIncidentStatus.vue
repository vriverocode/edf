<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useIncidentStore } from '@/services/store/incident.store'
import { Notify } from 'quasar'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const incidentStore = useIncidentStore()

const incident = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')

const statusOptions = [
  { value: 1, label: 'Pendiente', color: 'warning' },
  { value: 2, label: 'Atendido', color: 'info' },
  { value: 3, label: 'Pendiente de aprobación', color: 'blue-7' },
  { value: 4, label: 'Resuelto', color: 'positive' },
]

const selectedStatus = ref(null)

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2500 })
}

const submit = () => {
  if (selectedStatus.value === null || selectedStatus.value === '') {
    showNotify('negative', 'Selecciona el nuevo estado')
    return
  }

  submitting.value = true

  incidentStore
    .updateIncident(route.params.id, { status: Number(selectedStatus.value) })
    .then(() => {
      showNotify('positive', 'Estado actualizado con éxito')
      setTimeout(() => {
        router.push('/admin/incidents')
      }, 1000)
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al cambiar el estado de la incidencia')
    })
    .finally(() => {
      submitting.value = false
    })
}

const goBack = () => {
  router.push('/admin/incidents')
}

onMounted(async () => {
  try {
    incident.value = await incidentStore.getIncidentById(route.params.id)
    selectedStatus.value = String(incident.value.status)
  } catch (e) {
    error.value = e || 'Error al cargar la incidencia'
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
        <q-btn flat color="primary" label="Volver" @click="goBack" />
      </div>

      <div v-else-if="incident" class="px-4 py-6 md:px-28">
        <div class="text-center text-grey-9 text-h6 mb-4">{{ incident.title }}</div>

        <q-form @submit="submit()">
          <div class="row w-full">
            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="selectedDateBlock px-4 py-3">
                <div class="text-subtitle2 text-black mb-2">Resumen</div>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-grey-7">Tipo</span>
                  <span class="text-bold">{{ incidentStore.typeLabels[incident.type] }}</span>
                </div>
                <div class="flex justify-between items-center mb-1 mt-2">
                  <span class="text-grey-7">Reportado por</span>
                  <span class="text-bold">{{ incident.user?.name || '—' }}</span>
                </div>
                <div class="flex justify-between items-center mb-1 mt-2">
                  <span class="text-grey-7">Fecha</span>
                  <span class="text-bold">{{ moment(incident.date).format('DD/MM/YYYY') }}</span>
                </div>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-grey-7">Estado actual</span>
                  <q-chip
                    :color="statusOptions.find(o => o.value === Number(incident.status))?.color || 'grey-7'"
                    text-color="white"
                    class="text-bold px-3"
                    dense
                  >
                    {{ incident.status_label }}
                  </q-chip>
                </div>
              </div>
            </div>

            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Nuevo estado</div>
              <div class="mt-2">
                <q-radio
                  v-for="opt in statusOptions"
                  :key="opt.value"
                  v-model="selectedStatus"
                  :val="String(opt.value)"
                  :label="opt.label"
                  :color="opt.color"
                  class="col-12"
                />
              </div>
            </div>

            <div class="col-12 row mb-2 px-2 md:px-12 pt-8 pb-8">
              <div class="col-6 px-5">
                <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="goBack">
                  <div class="px-6 py-1">Volver</div>
                </q-btn>
              </div>
              <div class="col-6 px-5">
                <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="submitting">
                  <div class="px-6 py-1">Guardar</div>
                </q-btn>
              </div>
            </div>
          </div>
        </q-form>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.selectedDateBlock {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;
}
</style>