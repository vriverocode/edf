<script setup>
import { onMounted, ref } from 'vue';
import { Notify } from 'quasar'
import { useApartmentStore } from '@/services/store/apartment.store';
import { useRouter, useRoute } from 'vue-router';

const apartmentStore = useApartmentStore()
const router = useRouter()
const route = useRoute()
const loading = ref(false)

const formData = ref({
  number: '',
  address: '',
  block: '',
  area: '',
  description: '',
  floor: '',
  participation_percentage: ''
})

const getApartmentData = async () => {
  try {
    const id = route.params.id
    const response = await apartmentStore.getApartmentById(id)
    if (response.code === 200) {
      formData.value = {
        number: response.data.number,
        address: response.data.address,
        block: response.data.block,
        area: response.data.area,
        type: response.data.type,
        description: response.data.description,
        floor: response.data.floor,
        participation_percentage: response.data.participation_percentage
      }
    }
  } catch (error) {
    showNotify('negative', 'Error al cargar los datos del inmobiliario')
  }
}

const updateApartment = () => {
  loading.value = true
  apartmentStore.updateApartment(route.params.id, formData.value)
    .then((response) => {
      if (response.code !== 200) throw response
      showNotify('positive', 'Inmobiliario actualizado con éxito')
      setTimeout(() => {
        loading.value = false
        const page = route.query.page || ''
        const type = route.query.type || ''
        const search = route.query.search || ''
        const number = route.query.number || ''
        const params = []
        if (page) params.push(`page=${page}`)
        if (type) params.push(`type=${type}`)
        if (search) params.push(`search=${search}`)
        if (number) params.push(`number=${number}`)
        params.push(`highlight=${route.params.id}`)
        router.push(`/admin/department/list?${params.join('&')}`)
      }, 1000)
    })
    .catch(() => {
      loading.value = false
      showNotify('negative', 'No se pudo actualizar el inmobiliario')
    })
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

onMounted(() => {
  getApartmentData()
})
</script>

<template>
<div class="md:px-20 px-2  h-full" style="overflow: auto;">
  <div class="text-center text-black text-h5 text-bold md:mt-4 mt-8 md:mb-8 mb-4">
    Editar Inmobiliario
  </div>

  <q-form @submit="updateApartment">
    <div class="row pb-8">
      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Número de departamento</div>
        <q-input dense borderless v-model="formData.number" class="form__inputsR mt-1" color="primary" 
          :rules="[val => val && val.length > 0 || 'El número es necesario']" />
      </div>

      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Piso</div>
        <q-input dense borderless v-model="formData.floor" class="form__inputsR mt-1" type="number" color="primary"
          :rules="[val => val !== null && val !== '' || 'El piso es necesario']" />
      </div>

      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Dirección</div>
        <q-input dense borderless v-model="formData.address" class="form__inputsR mt-1" color="primary"
          :rules="[val => val && val.length > 0 || 'La dirección es necesaria']" />
      </div>

      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Torre / Bloque</div>
        <q-input dense borderless v-model="formData.block" class="form__inputsR mt-1" color="primary" />
      </div>

      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Medida en mt²</div>
        <q-input dense borderless v-model="formData.area" class="form__inputsR mt-1" type="number" color="primary"
          :rules="[val => val && (val + '').length > 0 || 'La medida es necesaria']" />
      </div>

      <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Porcentaje de participación (%)</div>
        <q-input dense borderless v-model="formData.participation_percentage" class="form__inputsR mt-1" 
          type="number" step="0.0000000001" color="primary"
          :rules="[val => val !== null && val !== '' || 'El porcentaje es necesario']" />
      </div>

      <div class="col-12 mt-1 px-2 md:px-12">
        <div class="text-subtitle2 text-black">Notas / Descripción</div>
        <q-input dense borderless v-model="formData.description" class="form__inputsR mt-1" color="primary" />
      </div>

      <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
        <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
          <div class="px-10 py-1">Guardar cambios</div>
        </q-btn>
      </div>
    </div>
  </q-form>
</div>
</template>

<style lang="scss">
.form__inputsR{
    & .q-field__inner {
      box-shadow: 0px 3px 4px 0px #bfbfbf48;
      border-radius: 0.5rem;
      border: 1px solid rgb(223, 223, 223);
      padding: 0px 1rem;
    }
  }
  @media (max-width: 780px) {
  .form__inputsR{
    & .q-field__inner {
  
      padding: 0.1rem 1rem;
    }
  }
  }
</style>