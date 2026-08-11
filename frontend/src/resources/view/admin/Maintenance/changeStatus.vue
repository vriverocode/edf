<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMaintenanceStore } from '@/services/store/maintenance.store'
import { Notify } from 'quasar'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const maintenanceStore = useMaintenanceStore()

const maintenance = ref(null)
const loading = ref(true)
const submitting = ref(false)
const error = ref('')

const statusOptions = [
  { value: 0, label: 'Cancelado', color: 'negative' },
  { value: 1, label: 'Pendiente', color: 'blue-7' },
  { value: 3, label: 'Pendiente de material', color: 'warning' },
  { value: 2, label: 'Completado', color: 'positive' },
]

const selectedStatus = ref(null)
const evidence = ref(null)
const description = ref('')

const isCompleting = computed(() => Number(selectedStatus.value) === 2)

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2500 })
}

const fileSizeInMB = computed(() => {
  if (!evidence.value) return 0
  const size = evidence.value.size / (1024 * 1024)
  return size.toFixed(2)
})

const handleUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      showNotify('negative', 'Por favor, selecciona solo un archivo de imagen.')
      return
    }
    if (file.size > 8 * 1024 * 1024) {
      showNotify('negative', 'La evidencia no puede superar los 8MB.')
      return
    }
    evidence.value = file
  }
}

const submit = () => {
  if (selectedStatus.value === null || selectedStatus.value === '') {
    showNotify('negative', 'Selecciona el nuevo estado')
    return
  }

  submitting.value = true

  const payload = new FormData()
  payload.append('status', selectedStatus.value)
  if (evidence.value) {
    payload.append('evidence', evidence.value)
  }
  if (description.value.trim()) {
    payload.append('description', description.value)
  }

  maintenanceStore.changeMaintenanceStatus(route.params.id, payload)
    .then(() => {
      showNotify('positive', 'Estado actualizado con éxito')
      setTimeout(() => {
        router.push(`/admin/maintenances/${route.params.id}`)
      }, 1000)
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al cambiar el estado del mantenimiento')
    })
    .finally(() => {
      submitting.value = false
    })
}

const goBack = () => {
  router.push(`/admin/maintenances/${route.params.id}`)
}

onMounted(async () => {
  try {
    maintenance.value = await maintenanceStore.getMaintenance(route.params.id)
    selectedStatus.value = String(maintenance.value.status)
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
        <q-btn flat color="primary" label="Volver" @click="goBack" />
      </div>

      <div v-else-if="maintenance" class="px-4 py-6 md:px-28">
        <div class="text-center text-grey-9 text-h6 mb-4">{{ maintenance.title }}</div>

        <q-form @submit="submit()">
          <div class="row w-full">
            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="selectedDateBlock px-4 py-3">
                <div class="text-subtitle2 text-black mb-2">Resumen</div>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-grey-7">Área</span>
                  <span class="text-bold">{{ maintenance.comun_area?.name || '—' }}</span>
                </div>
                <div class="flex justify-between items-center mb-1 mt-2">
                  <span class="text-grey-7">Fecha</span>
                  <span class="text-bold">{{ moment(maintenance.date).format('DD/MM/YYYY') }}</span>
                </div>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-grey-7">Estado actual</span>
                  <q-chip :color="statusOptions.find(o => o.value === Number(maintenance.status))?.color || 'grey-7'"
                    text-color="white" class="text-bold px-3" dense>
                    {{ maintenance.status_label }}
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

            <template v-if="isCompleting">
              <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
                <div class="text-subtitle2 text-black">Evidencia de finalización <span class="text-caption text-grey-6">(opcional)</span></div>
                <div class="photoContainer mt-1 px-3 w-full py-2">
                  <label for="maintenanceEvidence" class="cursor-pointer">
                    <template v-if="!evidence">
                      <div class="flex flex-center column">
                        <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
                        <div class="text-center">
                          <div class="text-grey-7 font-medium">
                            Sube la evidencia de que está completado
                          </div>
                          <div class="text-grey-6 font-medium">
                            Pulsa o haz click aqui para cargar tu archivo
                          </div>
                        </div>
                      </div>
                    </template>
                    <template v-else>
                      <div class="flex items-center justify-between">
                        <div class="flex items-center">
                          <q-icon color="tealedf" name="eva-checkmark-circle-2" />
                          <div class="ml-1">
                            <div class="text-xsImage text-tealedf">Evidencia de finalización</div>
                            <div class="text-xsImage text-black">
                              {{ evidence.name.slice(0, 10) }}***{{ evidence.name.slice(-5) }} - {{ fileSizeInMB }} MB
                            </div>
                          </div>
                        </div>
                      </div>
                    </template>
                  </label>
                  <input type="file" id="maintenanceEvidence" style="display: none;" accept="image/*" @change="handleUpload">
                </div>
              </div>

              <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
                <div class="text-subtitle2 text-black">Descripción corta <span class="text-caption text-grey-6">(opcional)</span></div>
                <q-input v-model="description" type="textarea" dense borderless
                  class="form__inputsR mt-1" color="primary"
                  placeholder="Describe brevemente el trabajo realizado..."
                  :rows="3"
                  maxlength="500" />
              </div>
            </template>

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
.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

.selectedDateBlock {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;
}

.photoContainer {
  border: 2px solid lightgray;
  border-radius: 1rem;
  cursor: pointer;
}

.text-xsImage {
  font-size: 0.75rem;
}

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
