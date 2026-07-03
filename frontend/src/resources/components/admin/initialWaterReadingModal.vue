<script setup>
import { ref, watch, computed } from 'vue'
import { Notify } from 'quasar'
import { useWaterReadingsStore } from '@/services/store/waterReadings.store'

const waterReadingsStore = useWaterReadingsStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  apartment: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['closeModal', 'created'])

const dialogVisible = ref(props.dialog)
const loading = ref(false)

const now = new Date()

const monthOptions = [
  { value: 1, name: 'Enero' },
  { value: 2, name: 'Febrero' },
  { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' },
  { value: 5, name: 'Mayo' },
  { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' },
  { value: 8, name: 'Agosto' },
  { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' },
  { value: 11, name: 'Noviembre' },
  { value: 12, name: 'Diciembre' }
]

const formData = ref({
  month: monthOptions[now.getMonth()],
  year: now.getFullYear(),
  previous_reading: '0',
  current_reading: '',
  photo: null,
  is_initial: false
})

const lastReadingLoading = ref(false)

const resetForm = () => {
  formData.value = {
    month: monthOptions[now.getMonth()],
    year: now.getFullYear(),
    previous_reading: '0',
    current_reading: '',
    photo: null,
    is_initial: false
  }
}

watch(() => formData.value.is_initial, async (isInitial) => {
  if (!isInitial && props.apartment?.id) {
    lastReadingLoading.value = true
    try {
      const { data } = await waterReadingsStore.getLastWaterReadingByDepartment(props.apartment.id)
      formData.value.previous_reading = data?.current_reading?.toString() || '0'
    } catch (e) {
      console.error(e)
      formData.value.previous_reading = '0'
    } finally {
      lastReadingLoading.value = false
    }
  } else {
    formData.value.previous_reading = '0'
  }
})

const parseMaskedDecimal = (value, decimals) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(decimals))
}

const fileSizeInMB = computed(() => {
  if (!formData.value.photo) return 0
  const size = formData.value.photo.size / (1024 * 1024)
  return size.toFixed(2)
})

const handleUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    if (!file.type.startsWith('image/')) {
      showNotify('warning', 'Selecciona solo archivos de imagen.')
      return
    }
    formData.value.photo = file
  }
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2200
  })
}

const close = () => {
  emit('closeModal')
}

const submit = () => {
  if (!formData.value.photo) {
    showNotify('warning', 'Debes subir la foto del medidor.')
    return
  }
  if (parseMaskedDecimal(formData.value.current_reading, 2) === null) {
    showNotify('warning', 'La lectura actual es requerida.')
    return
  }
  const prev = Number(formData.value.previous_reading) || 0
  const curr = parseMaskedDecimal(formData.value.current_reading, 2)
  if (curr <= prev) {
    showNotify('warning', 'La lectura actual debe ser mayor a la lectura anterior.')
    return
  }

  loading.value = true
  console.log(formData.value.month)
  const payload = new FormData()
  payload.append('departament_id', String(props.apartment?.id || ''))
  payload.append('month', String(formData.value.month?.value || ''))
  payload.append('year', String(Number(formData.value.year)))
  payload.append('previous_reading', String(parseMaskedDecimal(formData.value.previous_reading, 2)))
  payload.append('current_reading', String(parseMaskedDecimal(formData.value.current_reading, 2)))
  payload.append('is_initial', formData.value.is_initial ? '1' : '0')
  if (formData.value.photo) {
    payload.append('photo', formData.value.photo)
  }

  waterReadingsStore
    .createWaterReading(payload)
    .then((response) => {
      if (response?.code !== 200) throw response
      showNotify('positive', 'Medición registrada con éxito.')
      emit('created', response.data)
      close()
    })
    .catch((err) => {
      const msg = err?.error || err?.message || 'No se pudo registrar la medición.'
      showNotify('negative', msg)
    })
    .finally(() => {
      loading.value = false
    })
}

watch(
  () => props.dialog,
  async (open) => {
    dialogVisible.value = open
    if (open) {
      resetForm()
      if (props.apartment?.id) {
        lastReadingLoading.value = true
        try {
          const { data } = await waterReadingsStore.getLastWaterReadingByDepartment(props.apartment.id)
          formData.value.previous_reading = data?.current_reading?.toString() || '0'
        } catch (e) {
          console.error(e)
          formData.value.previous_reading = '0'
        } finally {
          lastReadingLoading.value = false
        }
      }
    }
  }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close" persistent>
    <q-card style="min-width: min(420px, 92vw);" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Registrar medición de agua</div>
      <div class="text-caption text-grey-7 q-mb-md" v-if="apartment">
        Unidad #{{ apartment.number }}
      </div>

      <div class="text-subtitle2 text-black">Mes</div>
      <q-select
        v-model="formData.month"
        :options="monthOptions"
        option-label="name"
        option-value="value"
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
      />

      <div class="text-subtitle2 text-black q-mt-md">Año</div>
      <q-input
        v-model.number="formData.year"
        type="number"
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
      />

      <div class="text-subtitle2 text-black q-mt-md">Lectura anterior</div>
      <q-input
        v-model="formData.previous_reading"
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
        mask="###.###.###,##"
        reverse-fill-mask inputmode="decimal"
        :disable="true"
        :readonly="true"
        :loading="lastReadingLoading"
      />

      <div class="text-subtitle2 text-black q-mt-md">Lectura actual</div>
      <q-input
        v-model="formData.current_reading"
        dense
        borderless
        class="form__inputsR mt-1"
        color="primary"
        mask="###.###.###,##"
        reverse-fill-mask inputmode="decimal"
        :rules="[val => parseMaskedDecimal(val, 2) !== null || 'La lectura actual es requerida',
        val => parseMaskedDecimal(val, 2) > parseMaskedDecimal(formData.previous_reading, 2) || 'La lectura actual debe ser mayor que la lectura anterior'
        ]" 
      />

      <div class="text-subtitle2 text-black q-mt-md">Foto del medidor</div>
      <div class="photoContainer px-3 py-2 mt-1">
        <label for="photoInputReading" class="cursor-pointer">
          <template v-if="!formData.photo">
            <div class="flex flex-center column">
              <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
              <div class="text-center">
                <div class="text-grey-7 font-medium">Sube la foto del medidor</div>
                <div class="text-grey-6 font-medium">Pulsa o haz click aquí para cargar tu archivo</div>
              </div>
            </div>
          </template>
          <template v-else>
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <q-icon color="teal" name="eva-checkmark-circle-2" />
                <div class="ml-1">
                  <div class="text-xs text-teal">Comprobante de medidor</div>
                  <div class="text-xs text-black">
                    {{ formData.photo.name.slice(0, 10) }}***{{ formData.photo.name.slice(-5) }} - {{ fileSizeInMB }} MB
                  </div>
                </div>
              </div>
            </div>
          </template>
        </label>
        <input type="file" id="photoInputReading" style="display: none;" accept="image/*" @change="handleUpload">
      </div>
      
      <div class="row items-center q-mt-md q-mb-sm">
        <q-checkbox
          v-model="formData.is_initial"
          label="Es lectura inicial"
          color="primary"
          @update:model-value="previousReadingByCheckStatus"
        />
      </div>
      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cancelar" color="grey" no-caps @click="close" :disable="loading" />
        <q-btn color="primary" label="Guardar" no-caps :loading="loading" @click="submit" />
      </div>
    </q-card>
  </q-dialog>
</template>

<style lang="scss" scoped>
.form__inputsR {
  & :deep(.q-field__inner) {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
    height: 45px;
  }
}

.photoContainer {
  border: 2px solid lightgray;
  border-radius: 1rem;
  cursor: pointer;
}
</style>
