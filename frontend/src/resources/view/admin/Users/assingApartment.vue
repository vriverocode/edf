<script setup>

import { onMounted, ref, watch} from 'vue';
import { Notify } from 'quasar'
import { useUserStore } from '@/services/store/users.store';
import { useApartmentStore } from '@/services/store/apartment.store';
import { useRoute, useRouter } from 'vue-router';
  const router = useRouter()
  const userStore = useUserStore()
  const apartmentStore = useApartmentStore()
  const apartmentsOptions = ref([])
  const getAvailableApartaments = () => {
    apartmentStore.getApartmentsByFind('available')
    .then((response) => {
      console.log(response)


      apartmentsOptions.value = [
        {
          id:0,
          number:'Selecciona un apartamento'
        },
        ...response.data
      ]
    })
  }
  const route = useRoute()
  const loading = ref(false)
  const formData = ref({
    user: route.params.id,
    apartment: {
      id:0,
      number:'Selecciona un apartamento'
    },
    idApartament:0
  })

  const assingApartment = () => {

    loading.value = true 

    formData.value.idApartament = formData.value.apartment.id

    userStore.assingApartment(formData.value)
    .then((response) =>{
      showNotify('positive', 'Apartamento asignado con exito')
      setTimeout(() => {
        loading.value = false
        router.go(-1)
      }, 1000);
    })
    .catch((response) => {
      console.log(response)
      loading.value = false

      showNotify('negative', response)

    })
  }


  const showNotify = (type,text) => {
    Notify.create({
      color:type,
      message: text,
      timeout:2000
    })
  }


  onMounted(() => {
    getAvailableApartaments()
  })
  
</script>
<template>
<div class="md:px-20 px-2  h-full">
  <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
    Asignación del inmobiliario
  </div>
  <q-form
  @submit="assingApartment()"
  class="h-full"
  >
    <div class="flex w-full h-5/6 column justify-between">
      <div class="my-1 px-2 md:px-12 w-full">
        <div class="text-subtitle2 text-bold text-black">
          Selecciona el apartamento
        </div>
        <q-select
          borderless
          class="form__inputsR mt-2"
          v-model="formData.apartment"
          option-value="id"
          option-label="number"
          :options="apartmentsOptions"
          behavior="menu"
        >

          <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps">
              <div class="w-full">
                <div class="flex items-center justify-between w-full">
                  <div class="text-subtitle1 " style="font-weight: 500;">
                    {{ scope.opt.id != 0 ? '#' :'' }} {{ scope.opt.number }}
                  </div>
                  <div v-if="scope.opt.id != 0" class="text-positive text-subtitle2 pl-2">
                    Disponible
                  </div>
                </div>
                <div class="text-caption text-grey-6" v-if="scope.opt.id != 0" >
                  {{ scope.opt.area }} mt²
                </div>
              </div>
            </q-item>
          </template>
        </q-select>
      </div>

      <div class="flex items-center justify-end mt-5 w-full">
        <div class="flex items-center justify-end px-2" style="width: 100%; box-sizing: border-box;">
          <q-btn color="primary " style="border-radius: 0.5rem; width: 100%;" type="submit" :loading="loading">
            <div class="px-8 py-1 " >
             Asignar
            </div>
          </q-btn>
        </div>
      </div>

    </div>
  </q-form>
</div>
</template>
<style lang="scss">
.form__inputsR{
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}
@media (max-width: 780px) {
  .form__inputsR{
    & .q-field__inner {

      padding: 0px 1rem;
    }
  }
}

</style>