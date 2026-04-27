<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useWaterReadingsStore } from '@/services/store/waterReadings.store'

const router = useRouter()
const apartmentStore = useApartmentStore()
const waterReadingsStore = useWaterReadingsStore()

const loading = ref(false)
const deptLoading = ref(false)

const parseMaskedDecimal = (value, decimals) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(decimals))
}

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

const now = new Date()

const departamentOptions = ref([])

const formData = ref({
  departament: null,
  month: monthOptions[now.getMonth()],
  year: now.getFullYear(),
  previous_reading: '',
  current_reading: '',
  photo: null
})

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const searchDepartaments = async () => {
  deptLoading.value = true
  try {
    const response = await apartmentStore.getApartmentsByFind('allWithUser')
    const items = response?.data || []
    departamentOptions.value = items.map((d) => ({
      label: `${d.number}`,
      value: d.id
    }))
  } catch (e) {
    departamentOptions.value = []
  } finally {
    deptLoading.value = false
  }
}

const submit = async () => {
  loading.value = true
  try {
    const payload = new FormData()
    payload.append('departament_id', String(formData.value.departament?.value || ''))
    payload.append('month', String(formData.value.month?.value || ''))
    payload.append('year', String(Number(formData.value.year)))
    payload.append('previous_reading', String(parseMaskedDecimal(formData.value.previous_reading, 2) ?? ''))
    payload.append('current_reading', String(parseMaskedDecimal(formData.value.current_reading, 2) ?? ''))
    if (formData.value.photo) {
      payload.append('photo', formData.value.photo)
    }

    const response = await waterReadingsStore.createWaterReading(payload)
    if (response?.code !== 200) throw response

    showNotify('positive', 'Medición registrada con éxito')
    router.push('/admin/water_readings/list')
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo registrar la medición')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  searchDepartaments('')
})
</script>

<template>
  <div class="md:px-20 md:mx-16 px-2 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Registrar medición de agua
    </div>

    <q-form @submit="submit()">
      <div class="row w-full">
        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Departamento</div>
          <q-select
            dense
            borderless
            class="form__inputsR mt-1"
            v-model="formData.departament"
            :options="departamentOptions"
            option-label="label"
            option-value="value"
            :loading="deptLoading"
            :rules="[val => !!val || 'El departamento es requerido']"
          />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select
            dense
            borderless
            class="form__inputsR mt-1"
            v-model="formData.month"
            :options="monthOptions"
            option-label="name"
            option-value="value"
            :rules="[val => !!val || 'El mes es requerido']"
          />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input
            dense
            borderless
            clearable
            class="form__inputsR mt-1"
            color="primary"
            type="number"
            v-model.number="formData.year"
            :rules="[val => !!val || 'El año es requerido']"
          />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Lectura anterior</div>
          <q-input
            dense
            borderless
            clearable
            class="form__inputsR mt-1"
            v-model="formData.previous_reading"
            mask="###.###.###,##"
            reverse-fill-mask
            inputmode="decimal"
            :rules="[val => parseMaskedDecimal(val, 2) !== null || 'La lectura anterior es requerida']"
          />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Lectura actual</div>
          <q-input
            dense
            borderless
            clearable
            class="form__inputsR mt-1"
            v-model="formData.current_reading"
            mask="###.###.###,##"
            reverse-fill-mask
            inputmode="decimal"
            :rules="[val => parseMaskedDecimal(val, 2) !== null || 'La lectura actual es requerida']"
          />
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Foto comprobante del medidor</div>
          <q-file
            dense
            borderless
            clearable
            class="form__inputsR mt-1"
            v-model="formData.photo"
            accept=".jpg,.jpeg,.png,.webp,image/*"
            :rules="[val => !!val || 'La foto comprobante es requerida']"
          />
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar</div>
          </q-btn>
        </div>
      </div>
    </q-form>
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

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>

