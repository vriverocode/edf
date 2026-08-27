<script setup>
import { onMounted, ref, computed } from 'vue'
import { Notify } from 'quasar'
import { useRouter, useRoute } from 'vue-router'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useWaterReadingsStore } from '@/services/store/waterReadings.store'

const router = useRouter()
const route = useRoute()
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
const sequential = ref(route.query.sequential === '1')
const navigatingNext = ref(false)

const formData = ref({
  departament: null,
  is_common: false,
  month: sequential.value
    ? monthOptions.find(m => m.value === Number(route.query.month)) || monthOptions[now.getMonth() - 1]
    : monthOptions[now.getMonth() - 1],
  year: sequential.value ? Number(route.query.year) || now.getFullYear() : now.getFullYear(),
  previous_reading: '',
  current_reading: '',
  photo: null
})

const sequentialLabel = computed(() => {
  if (!sequential.value) return ''
  return `Modo secuencial — ${departamentOptions.value.findIndex(o => o.value === formData.value.departament?.value) + 1} de ${departamentOptions.value.length}`
})

const hasNext = computed(() => {
  if (!sequential.value) return false
  const idx = departamentOptions.value.findIndex(o => o.value === formData.value.departament?.value)
  return idx < departamentOptions.value.length - 1
})

const hasPrev = computed(() => {
  if (!sequential.value) return false
  const idx = departamentOptions.value.findIndex(o => o.value === formData.value.departament?.value)
  return idx > 0
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
    const response = await apartmentStore.getApartmentsByFind('allDepartmentWithoutReadingThisMonth')
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
  if (!validateForm()) {
    return
  }
  loading.value = true
  try {
    const payload = new FormData()
    if (!formData.value.is_common) {
      payload.append('departament_id', String(formData.value.departament?.value || ''))
    }
    payload.append('is_common', formData.value.is_common ? '1' : '0')
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

    if (sequential.value && hasNext.value) {
      navigatingNext.value = true
      const nextIdx = departamentOptions.value.findIndex(o => o.value === formData.value.departament?.value) + 1
      const nextDepartment = departamentOptions.value[nextIdx]
      formData.value = {
        departament: nextDepartment,
        month: formData.value.month,
        year: formData.value.year,
        previous_reading: '',
        current_reading: '',
        photo: null
      }
      navigatingNext.value = false
      loading.value = false
      return
    } else if (sequential.value) {
      showNotify('positive', 'Todas las lecturas del período están registradas')
    }
    router.go(-1)
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo registrar la medición')
  } finally {
    loading.value = false
  }
}
const validateForm = () => {
  if (!formData.value.photo) {
    showNotify('negative', 'Debes subir la foto del medidor')
    return false
  };
  return true;
}
const handleUpload = (event) => {
  const file = event.target.files[0];

  if (file) {
    if (!file.type.startsWith('image/')) {
      showNotify('negative', 'Por favor, selecciona solo un archivo de imagen.');
      return;
    }
    formData.value.photo = file;

  }
};
const fileSizeInMB = computed(() => {
  if (!formData.value.photo) return 0;

  // Convertir bytes a Megabytes (bytes / 1024 = KB -> KB / 1024 = MB)
  const size = formData.value.photo.size / (1024 * 1024);

  // .toFixed(2) recorta los decimales para que se vea limpio (ej: 1.45)
  return size.toFixed(2);
});
const navigateDept = (direction) => {
  const idx = departamentOptions.value.findIndex(o => o.value === formData.value.departament?.value)
  const target = direction === 'next' ? idx + 1 : idx - 1
  if (target < 0 || target >= departamentOptions.value.length) return
  formData.value = {
    departament: departamentOptions.value[target],
    month: formData.value.month,
    year: formData.value.year,
    previous_reading: '',
    current_reading: '',
    photo: null
  }
}

onMounted(() => {
  searchDepartaments('')
  if (sequential.value && route.query.department_id) {
    const dept = departamentOptions.value.find(o => o.value === Number(route.query.department_id))
    if (dept) formData.value.departament = dept
  }
})


</script>

<template>
  <div class="md:px-20 px-2  h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Registrar medición de agua
    </div>

    <q-form @submit="submit()">
      <div class="row w-full">
        <div class="col-12 mt-1 px-2 md:px-12">
          <q-toggle v-model="formData.is_common" label="Es lectura de áreas comunes" color="primary" />
        </div>

        <div v-if="!formData.is_common" class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Departamento</div>
          <q-select dense borderless class="form__inputsR mt-1" v-model="formData.departament"
            :options="departamentOptions" option-label="label" option-value="value" :loading="deptLoading"
            :rules="[val => !!val || 'El departamento es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select dense borderless class="form__inputsR mt-1" v-model="formData.month" :options="monthOptions"
            option-label="name" option-value="value" :rules="[val => !!val || 'El mes es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number"
            v-model.number="formData.year" :rules="[val => !!val || 'El año es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Lectura anterior</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.previous_reading"
            mask="###.###.###,###" reverse-fill-mask inputmode="decimal"
            :rules="[val => parseMaskedDecimal(val, 2) !== null || 'La lectura anterior es requerida']" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Lectura actual</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" v-model="formData.current_reading"
            mask="###.###.###,###" reverse-fill-mask inputmode="decimal" :rules="[val => parseMaskedDecimal(val, 2) !== null || 'La lectura actual es requerida',
            val => parseMaskedDecimal(val, 2) > parseMaskedDecimal(formData.previous_reading, 2) || 'La lectura actual debe ser mayor que la lectura anterior'
            ]" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class=" photoContainer mt-0 px-3 w-full py-2">
            <label for="vaucherPay" class="cursor-pointer">
              <template v-if="!formData.photo">
                <div class=" flex flex-center column">

                  <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
                  <div class="text-center">
                    <div class="text-grey-7 font-medium">
                      Sube la foto del medidor
                    </div>
                    <div class="text-grey-6 font-medium">
                      Pulsa o haz click aqui para carga tu archivo
                    </div>
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="flex items-center justify-between">
                  <div class="flex items-center">
                    <q-icon color="tealedf" name="eva-checkmark-circle-2" />
                    <div class="ml-1">
                      <div class="text-xsImage text-tealedf">Comprobante de medidor</div>
                      <div class="text-xsImage text-black"> {{ formData.photo.name.slice(0, 10)
                      }}***{{
                          formData.photo.name.slice(-5) }} - {{ fileSizeInMB }} MB</div>
                    </div>
                  </div>
                </div>
              </template>
            </label>
            <input type="file" id="vaucherPay" style="display: none;" accept="image/*" @change="handleUpload">
            <div></div>
          </div>
        </div>


        <div v-if="sequential" class="col-12 px-2 md:px-12 q-mt-md">
          <q-banner class="bg-primary text-white rounded-borders text-center">
            {{ sequentialLabel }}
          </q-banner>
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex items-center justify-between mt-4 ">
          <q-btn outline v-if="sequential && hasPrev" style="border-radius: 0.5rem;" class="px-2" color="grey-7" @click="navigateDept('prev')"
            :disable="loading || navigatingNext">
            <div class="px-4">
              Anterior
            </div>
          </q-btn>
          <q-btn color="primary" 
          :class="{'w-full':sequential && !hasPrev}" 
          style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-2 py-1 ">{{ sequential && hasNext ? 'Guardar y siguiente' : 'Guardar' }}</div>
          </q-btn>
        </div>
      </div>
    </q-form>
  </div>
</template>

<style lang="scss">
.photoContainer {
  border: 2px solid lightgray;
  border-radius: 1rem;
  cursor: pointer;
}

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
