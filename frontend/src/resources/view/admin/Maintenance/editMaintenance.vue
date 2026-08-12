<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMaintenanceStore } from '@/services/store/maintenance.store'
import { Notify } from 'quasar'
import moment from 'moment'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const myLocale = {
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'DO_LU_MA_MI_JU_VI_SA'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  format24h: true,
  pluralDay: 'dias'
}

const route = useRoute()
const router = useRouter()
const maintenanceStore = useMaintenanceStore()

const loading = ref(false)
const ready = ref(false)
const maintenance = ref(null)

const formData = ref({
  motive: '',
  date: '',
  date_to: '',
  start_time: '',
  end_time: '',
  photo: null,
})

const isSingleDay = computed(() => {
  return !formData.value.date_to || formData.value.date_to === formData.value.date
})

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const dateOptions = (date) => {
  return date >= moment().format('YYYY/MM/DD')
}

const loadMaintenance = () => {
  ready.value = false
  maintenanceStore.getMaintenance(route.params.id)
    .then((data) => {
      maintenance.value = data
      formData.value.motive = data.description || ''
      formData.value.date = data.date
      formData.value.start_time = data.time_from ? data.time_from.substring(0, 5) : ''
      formData.value.end_time = data.time_to ? data.time_to.substring(0, 5) : ''
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al cargar el mantenimiento')
    })
    .finally(() => {
      ready.value = true
    })
}

const submit = () => {
  if (!formData.value.motive.trim()) {
    showNotify('negative', 'El motivo es requerido')
    return
  }
  if (!formData.value.date) {
    showNotify('negative', 'Selecciona la fecha de inicio')
    return
  }
  if (formData.value.date_to && formData.value.date_to < formData.value.date) {
    showNotify('negative', 'La fecha de fin debe ser igual o posterior a la de inicio')
    return
  }
  if (isSingleDay.value) {
    if (!formData.value.start_time) {
      showNotify('negative', 'Selecciona la hora de inicio')
      return
    }
    if (!formData.value.end_time) {
      showNotify('negative', 'Selecciona la hora de fin')
      return
    }
    if (formData.value.start_time >= formData.value.end_time) {
      showNotify('negative', 'La hora de fin debe ser posterior a la de inicio')
      return
    }
  }

  loading.value = true

  const payload = new FormData()
  payload.append('motive', formData.value.motive)
  payload.append('date', formData.value.date)
  if (formData.value.date_to) {
    payload.append('date_to', formData.value.date_to)
  }
  if (formData.value.start_time) {
    payload.append('time_from', formData.value.start_time)
  }
  if (formData.value.end_time) {
    payload.append('time_to', formData.value.end_time)
  }
  if (formData.value.photo) {
    payload.append('photo', formData.value.photo)
  }

  maintenanceStore.updateMaintenance(route.params.id, payload)
    .then(() => {
      showNotify('positive', 'Mantenimiento actualizado con éxito')
      setTimeout(() => {
        router.push(`/admin/maintenances/${route.params.id}`)
      }, 1000)
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al actualizar mantenimiento')
    })
    .finally(() => {
      loading.value = false
    })
}

const goBack = () => {
  router.push(`/admin/maintenances/${route.params.id}`)
}

const handleUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      showNotify('negative', 'Por favor, selecciona solo un archivo de imagen.')
      return
    }
    if (file.size > 8 * 1024 * 1024) {
      showNotify('negative', 'La foto no puede superar los 8MB.')
      return
    }
    formData.value.photo = file
  }
}

const fileSizeInMB = computed(() => {
  if (!formData.value.photo) return 0
  const size = formData.value.photo.size / (1024 * 1024)
  return size.toFixed(2)
})

onMounted(() => {
  loadMaintenance()
})
</script>

<template>
  <div class="md:px-20 px-2 h-full" style="overflow: auto;">

    <div class="text-center text-grey-9 text-h6 mb-4" v-if="maintenance">
      {{ maintenance.title }}
    </div>

    <div v-if="!ready" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>

    <q-form v-else @submit="submit()">
      <div class="row w-full">
        <!-- Fecha inicio -->
        <div class="col-md-6 col-12 mt-0 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Fecha de inicio</div>
          <q-input v-model="formData.date" dense borderless readonly clearable
            class="form__inputsR mt-1" color="primary"
            placeholder="Selecciona la fecha">
            <template v-slot:append>
              <q-icon name="eva-calendar-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="formData.date" mask="YYYY-MM-DD"
                    :options="dateOptions" :locale="myLocale" color="primary">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Aceptar" color="primary" flat />
                    </div>
                  </q-date>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <!-- Fecha fin -->
        <div class="col-md-6 col-12 md:mt-0 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Fecha de fin <span class="text-caption text-grey-6">(opcional)</span></div>
          <q-input v-model="formData.date_to" dense borderless readonly clearable
            class="form__inputsR mt-1" color="primary"
            placeholder="Selecciona la fecha de fin">
            <template v-slot:append>
              <q-icon name="eva-calendar-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="formData.date_to" mask="YYYY-MM-DD"
                    :options="dateOptions" :locale="myLocale" color="primary">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Aceptar" color="primary" flat />
                    </div>
                  </q-date>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <!-- Hora inicio -->
        <div class="col-md-3 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Hora de inicio</div>
          <q-input v-model="formData.start_time" dense borderless mask="time"
            class="form__inputsR mt-1" color="primary" placeholder="HH:MM">
            <template v-slot:append>
              <q-icon name="eva-clock-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-time v-model="formData.start_time" format24h color="primary">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Aceptar" color="primary" flat />
                    </div>
                  </q-time>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <!-- Hora fin -->
        <div class="col-md-3 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Hora de fin</div>
          <q-input v-model="formData.end_time" dense borderless mask="time"
            class="form__inputsR mt-1" color="primary" placeholder="HH:MM">
            <template v-slot:append>
              <q-icon name="eva-clock-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-time v-model="formData.end_time" format24h color="primary">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Aceptar" color="primary" flat />
                    </div>
                  </q-time>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <!-- Foto -->
        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Foto (opcional)</div>
          <div class="photoContainer mt-1 px-3 w-full py-2">
            <label for="maintenancePhoto" class="cursor-pointer">
              <template v-if="!formData.photo && !maintenance.photo">
                <div class="flex flex-center column">
                  <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
                  <div class="text-center">
                    <div class="text-grey-7 font-medium">
                      Sube una foto del mantenimiento
                    </div>
                    <div class="text-grey-6 font-medium">
                      Pulsa o haz click aqui para cargar tu archivo
                    </div>
                  </div>
                </div>
              </template>
              <template v-else-if="formData.photo">
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <q-icon color="tealedf" name="eva-checkmark-circle-2" />
                    <div class="ml-1">
                      <div class="text-xsImage text-tealedf">Foto del mantenimiento</div>
                      <div class="text-xsImage text-black">
                        {{ formData.photo.name.slice(0, 10) }}***{{ formData.photo.name.slice(-5) }} - {{ fileSizeInMB }} MB
                      </div>
                    </div>
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="flex flex-center column">
                  <q-img :src="maintenance.photo" spinner-color="primary" style="max-width: 12rem; border-radius: 0.5rem;" />
                  <div class="text-grey-6 font-medium mt-2">
                    Pulsa para reemplazar la foto
                  </div>
                </div>
              </template>
            </label>
            <input type="file" id="maintenancePhoto" style="display: none;" accept="image/*" @change="handleUpload">
          </div>
        </div>

        <!-- Motivo -->
        <div class="col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Motivo del mantenimiento</div>
          <q-input v-model="formData.motive" type="textarea" dense borderless
            class="form__inputsR mt-1" color="primary"
            placeholder="Describe el motivo del mantenimiento..."
            :rows="3"
            maxlength="500" />
        </div>

        <!-- Aviso conflictos -->
        <div class="col-12 mt-3 px-2 md:px-12" v-if="formData.date">
          <q-banner class="bg-orange-2 text-orange-9 rounded-lg" inline-actions>
            <template v-slot:avatar>
              <q-icon name="eva-alert-triangle-outline" color="orange" size="sm" />
            </template>
            Se cancelarán automáticamente las reservas que coincidan con el nuevo horario del mantenimiento.
          </q-banner>
        </div>

        <!-- Botones -->
        <div class="col-12 row mb-2 px-2 md:px-12 pt-8 pb-8">
          <div class="col-12 px-0">
            <q-btn color="primary" class="w-full" no-caps style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="px-6 py-1 ">Guardar cambios</div>
            </q-btn>
          </div>
        </div>
      </div>
    </q-form>
  </div>
</template>

<style lang="scss">
.gap-2 {
  gap: 0.5rem;
}

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
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