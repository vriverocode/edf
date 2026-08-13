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

const formData = ref({
  evidence: null,
  description: '',
})

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2500 })
}

const fileSizeInMB = computed(() => {
  if (!formData.value.evidence) return 0
  const size = formData.value.evidence.size / (1024 * 1024)
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
    formData.value.evidence = file
  }
}

const submit = () => {
  if (!formData.value.evidence) {
    showNotify('negative', 'Debes adjuntar una evidencia de finalización')
    return
  }
  if (!formData.value.description.trim()) {
    showNotify('negative', 'La descripción corta es requerida')
    return
  }

  submitting.value = true

  const payload = new FormData()
  payload.append('evidence', formData.value.evidence)
  payload.append('description', formData.value.description)

  maintenanceStore.completeMaintenance(route.params.id, payload)
    .then(() => {
      showNotify('positive', 'Mantenimiento completado con éxito')
      setTimeout(() => {
        router.go(-1)
      }, 1000)
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al completar el mantenimiento')
    })
    .finally(() => {
      submitting.value = false
    })
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
                  <span class="text-grey-7">Horario</span>
                  <span v-if="maintenance.time_from && maintenance.time_to" class="text-bold">
                    {{ maintenance.time_from.substring(0, 5) }} - {{ maintenance.time_to.substring(0, 5) }}
                  </span>
                  <span v-else class="text-bold">Todo el día</span>
                </div>
                <div class="flex justify-between items-center mb-1">
                  <span class="text-grey-7">Estado</span>
                  <span class="text-bold">{{ maintenance.status_label }}</span>
                </div>
              </div>
            </div>

            <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Evidencia de finalización</div>
              <div class="photoContainer mt-1 px-3 w-full py-2">
                <label for="maintenanceEvidence" class="cursor-pointer">
                  <template v-if="!formData.evidence">
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
                            {{ formData.evidence.name.slice(0, 10) }}***{{ formData.evidence.name.slice(-5) }} - {{ fileSizeInMB }} MB
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                </label>
                <input type="file" id="maintenanceEvidence" style="display: none;" accept="image/*" @change="handleUpload">
              </div>
            </div>

            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Descripción corta</div>
              <q-input v-model="formData.description" type="textarea" dense borderless
                class="form__inputsR mt-1" color="primary"
                placeholder="Describe brevemente el trabajo realizado..."
                :rows="3"
                maxlength="500" />
            </div>

            <div class="col-12 row mb-2 px-2 md:px-12 pt-8 pb-8">
              <div class="col-12 px-5">
                <q-btn color="primary" style="border-radius: 0.5rem;" class="w-full" type="submit" :loading="submitting">
                  <div class="px-6 py-1">Completar</div>
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
