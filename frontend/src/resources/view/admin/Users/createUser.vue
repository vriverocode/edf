<script setup>

import { onMounted, ref, watch} from 'vue';
import { Notify } from 'quasar'
import { useUserStore } from '@/services/store/users.store';
import { useApartmentStore } from '@/services/store/apartment.store';
import phoneNumberInput from '@/components/layout/phoneNumberInput.vue';
import { useRouter } from 'vue-router';
  const router = useRouter()
  const userStore = useUserStore()
  const apartmentStore = useApartmentStore()
  const apartmentsOptions = ref([])
  const rolOptions = ref([
    {
      id:0,
      title:'Selecciona el tipo de usuario'
    },
    {
      id:2,
      title:'Propietario'
    },
    {
      id:3,
      title:'Inquilino'
    },
    {
      id:6,
      title:'Trabajador'
    },
    {
      id:7,
      title:'Propietario Parcial'
    },
  ])

  const getAvailableApartaments = () => {
    apartmentStore.getApartmentsByFind('available')
    .then((response) => {
      console.log(response)
      apartmentsOptions.value = [
        {
          id:0,
          number:'Selecciona un departamento'
        },
        ...response.data
      ]
    })
  }
  const props = defineProps({
    dialog: Boolean,
    rifa: Object,
  })

  const isPwd =  ref('true')

  const step = ref(0)
  const loading = ref(false)
  const formData = ref({
    name:'',
    username:'',
    email:'',
    phone:'',
    password:'',
    rol_id: {
      id:0,
      title:'Selecciona el tipo de usuario'
    },
    apartment: {
      id:0,
      number:'Selecciona un departamento'
    },
    idApartament:0,
    idRol:1

  })

  const titleOfSection = [ 
    'Datos del usuario',
    'Asignación del inmobiliario'
  ]


  const createUser = () => {

    if(step.value == 0 ) {
      step.value++
      return 
    }

    loading.value = true 
    formData.value.idApartament = formData.value.apartment.id
    formData.value.idRol = formData.value.rol_id.id


    userStore.createUser(formData.value)
    .then((response) =>{
      showNotify('positive', 'Usuario creado con exito')
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
<div class="md:px-20 px-2">
  <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
    {{ titleOfSection[step]}}
  </div>
  <q-form
  @submit="createUser()"
  >
    <Transition name="horizontal">
      <div class="row w-full" v-if="step==0">
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Nombre completo
          </div>
          <q-input
              borderless
              dense
              clearable
              v-model="formData.name"
              class="form__inputsCR mt-2"
              color="primary"
              :rules="[ val => val && val.length > 0 || 'Nombre es requerido']"
            />
        </div>
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Nombre de usuario
          </div>
          <q-input
              borderless
              dense
              clearable
              v-model="formData.username"
              class="form__inputsCR mt-2"
              color="primary"
              :rules="[ val => val && val.length > 0 || 'Nombre de usuario es requerido']"
            />
        </div>
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Correo electronico
          </div>
          <q-input
              borderless
              dense
              clearable
              v-model="formData.email"
              class="form__inputsCR mt-2"
              color="primary"
              :rules="[ val => val && val.length > 0 || 'Correo electronico es requerido']"
            />
        </div>
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Contraseña
          </div>
          <q-input
            borderless
            dense
            clearable
            v-model="formData.password"
            class="form__inputsCR mt-2"
            color="primary"
            :type="isPwd ? 'password' : 'text'" 
            :rules="[ val => val && val.length > 0 || 'Contraseña es requerida']"
          >
            <template v-slot:append>
              <q-icon
                :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                class="cursor-pointer"
                @click="isPwd = !isPwd"
              />
            </template>
          </q-input>
          
        </div>
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Telefono
          </div>
          <phoneNumberInput 
            v-model="formData.phone"
            label="Tu Teléfono"
            placeholder="412-1234567"
            class="phoneUser"
            :rules="[val => !!val || 'El teléfono es requerido']"
          />
        </div>
        
        <div class="col-12 my-2 px-2 md:px-12 pb-8 flex justify-end">
          <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1" >
              Siguiente
            </div>
          </q-btn>
        </div>

      </div>
    </Transition>
    <Transition name="horizontal">
      <div class="row w-full" v-if="step==1">
        <div class="col-md-6 md:my-0 col-12 my-1 mb-4 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Tipo de usuario
          </div>
          <q-select
            borderless
            class="form__inputsCR mt-2"
            v-model="formData.rol_id"
            option-value="id"
            option-label="title"
            :options="rolOptions"
            behavior="menu"
            dense
          >
          </q-select>
        </div>
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Selecciona el departamento
          </div>
          <q-select
            borderless
            class="form__inputsCR mt-2"
            v-model="formData.apartment"
            option-value="id"
            option-label="number"
            :options="apartmentsOptions"
            behavior="menu"
            dense
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

        <div class="col-12 mb-2 mt-5 px-2 md:px-12 flex items-center justify-between">
          <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
            <q-btn color="grey-9 " style="border-radius: 0.5rem;" @click="step--">
              <div class="px-8 py-1 " >
                Volver
              </div>
            </q-btn>
          </div>
          <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
            <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="px-8 py-1 " >
                Siguiente
              </div>
            </q-btn>
          </div>
        </div>

      </div>
    </Transition>
  </q-form>
</div>
</template>
<style lang="scss">
.phoneUser.form__inputsSelect .prefixInput .q-field__inner{
  border: 0px solid rgb(223, 223, 223);
}
.form__inputsCR{
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}
@media (max-width: 780px) {
  .form__inputsCR{
    & .q-field__inner {

      padding: 0px 1rem;
    }
  }
}

</style>