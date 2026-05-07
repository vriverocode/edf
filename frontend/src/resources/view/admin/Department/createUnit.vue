<script setup>
import { ref } from 'vue';
import { Notify } from 'quasar'
import { useApartmentStore } from '@/services/store/apartment.store';
import { useRouter } from 'vue-router';

const apartmentStore = useApartmentStore()
const router = useRouter()
const loading = ref(false)

const unitTypesOptions = [
  { label: '🏢 Departamento', value: 1 },
  { label: '🚗 Estacionamiento', value: 2 },
  { label: '📦 Depósito', value: 3 }
]

const formData = ref({
  type: 1, // Por defecto inicia en Departamento
  number: '',
  participation_percentage: '',
  description: '',
  // Campos exclusivos de Departamento
  address: '',
  block: '',
  area: '',
  floor: ''
})

const createApartment = () => {
  loading.value = true
  apartmentStore.createApartment(formData.value)
    .then((response) => {
      if (response.code !== 200) throw response
      Notify.create({ color: 'positive', message: 'Unidad creada con éxito', timeout: 2000 })
      setTimeout(() => {
        loading.value = false
        router.go(-1)
      }, 1000)
    })
    .catch(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="md:px-20 md:mx-16 px-2 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-8 md:mb-8 mb-4">
      Registro de Inmobiliario
    </div>

    <q-form @submit="createApartment" class="pb-8">

      <div class="row w-full">

        <div class="col-12 mt-1 px-2 md:px-12 mb-2">
          <div class="text-subtitle2 text-black">
            Tipo de unidad
          </div>
          <q-select dense borderless v-model="formData.type" :options="unitTypesOptions" emit-value map-options
            class="form__inputsR mt-1" />
        </div>

        <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
          <div class="text-subtitle2 text-black">
            N° de unidad
          </div>
          <q-input dense borderless clearable v-model="formData.number" class="form__inputsR mt-1" color="primary"
            :rules="[val => val && val.length > 0 || 'El número de unidad es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Porcentaje de participación (%)</div>
          <q-input dense borderless clearable v-model="formData.participation_percentage" class="form__inputsR mt-1"
            type="number" step="0.0000000001"
            :rules="[val => val !== null && val !== '' || 'El porcentaje es necesario']" />
        </div>

        <transition appear enter-active-class="animated fadeIn" leave-active-class="animated fadeOut">
          <div v-if="formData.type === 1" class="col-12 row">
            <div class=" col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Ubicación
              </div>
              <q-input dense borderless clearable v-model="formData.address" class="form__inputsR mt-1" color="primary"
                :rules="[val => val && val.length > 0 || 'La ubicación es requerida']" />
            </div>
            <div class="col-md-6 col-12 mt-1 mb-5 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                N° de bloque
              </div>
              <q-input dense borderless clearable v-model="formData.block" type="number" class="form__inputsR mt-1"
                color="primary" />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                N° de Piso
              </div>
              <q-input dense borderless clearable v-model="formData.floor" class="form__inputsR mt-1" type="number"
                color="primary" :rules="[val => val && (val + '').length > 0 || 'Número de piso es necesario']" />

            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Medida en mt²
              </div>
              <q-input dense borderless clearable v-model="formData.area" class="form__inputsR mt-1" type="number"
                color="primary" :rules="[val => val && (val + '').length > 0 || 'Medida en mt² es necesaria']" />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Notas
              </div>
              <q-input dense borderless clearable v-model="formData.description" class="form__inputsR mt-1"
                color="primary" />
            </div>

          </div>
        </transition>
        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">
              Siguiente
            </div>
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