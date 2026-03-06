<script setup>
import { onMounted, ref, watch} from 'vue';
import { Notify } from 'quasar'
import { useRouter } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';

  const comunAreaStore = useComunAreaStore()
  const router = useRouter()
  const loading = ref(false)
  const step = ref(0)
  const iconsOption = [
    { value: 'default', name:'Por defecto'},
    { value: 'arcade', name:'Arcade'},
    { value: 'cine', name:'Cine'},
    { value: 'coworking', name:'Coworking'},
    { value: 'gimnasio', name:'Gimnasio'},
    { value: 'juego_de_salon', name:'Juego de salón'},
    { value: 'lounge', name:'Lounge'},
    { value: 'parrilla', name:'Parilla'},
    { value: 'piscina', name:'Piscina'},
    { value: 'sauna', name:'Sauna'},
  ]
  const formData = ref({
    name: '',
    capacity: '',
    price: 0,
    warrantyPrice: 0,
    description:'',
    maxTime: 1,
    timeFrom: '',
    timeTo: '',
    rules: '',
    icon:{ value: 'default', name:'Por defecto'},
  })

  const backButton = () => {
    step.value--
  } 
  const nextStep = () => {
    if(step.value == 1) {
      createArea()
      return
    }

    step.value++
  }
  const createArea = () => {
    loading.value = true 
    formData.value.imageIcon = formData.value.icon.value;
    comunAreaStore.createComunArea(formData.value)
    .then((response) => {
      if(response.code !==200) throw response 

      
      showNotify('positive', 'Area común creada con exito')
      setTimeout(()=>{
        loading.value = false
        router.go(-1)

      },1000)
    })
    .catch((response) =>{
      loading.value = false

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
  })
  
</script>
<template>
<div class="md:px-20 md:mx-16 px-2 h-full" style="overflow: hidden; ">
  <div class="text-center text-black text-h5 text-bold  md:mb-8 mb-4">
    Datos del area común
  </div>
  <q-form
    @submit="nextStep()"
    class="h-full"
  >

    <div class=" w-full h-full" >
      <Transition name="horizontal">

        <div class="row w-full" style="height:82%; overflow:auto" v-if="step==0">
          <div class="col-md-6 col-12 mt-1 mb-4 px-2 md:px-12">
            
            <div class="boxImgStore">
              <img :src="'http://192.168.1.198:8030/images/icons/'+formData.icon.value+'.svg'" alt="">
            </div>
          </div>
          <div class="col-md-6 col-12 mt-1 mb-4 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Icono
            </div>
            <q-select 
              class="form__inputsR mt-1"
              v-model="formData.icon"
              :options="iconsOption"
              option-label="name"
              option-value="value"
              dense borderless 
            />
          </div>
          <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Nombre del area
            </div>
            <q-input
              dense
              borderless
              clearable
              v-model="formData.name"
              class="form__inputsR mt-1"
              color="primary"
              :rules="[ val => val && val.length > 0 || 'Nombre de area es requerido']"
            />
          </div>
          <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Aforo
            </div>
            <q-input
                dense
                borderless
                clearable
                v-model="formData.capacity"
                class="form__inputsR mt-1"
                color="primary"
                :rules="[ val => !(!val) || 'El aforo es requerido']"
              />
          </div>
          <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Precio por reserva
            </div>
            <q-input
                dense
                borderless
                clearable
                v-model="formData.price"
                class="form__inputsR mt-1"
                color="primary"
                hint="Dejar en 0 si no requiere reserva"
              />
          </div>
          <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Precio de garantia
            </div>
            <q-input
                dense
                borderless
                clearable
                v-model="formData.warrantyPrice"
                class="form__inputsR mt-1"
                color="primary"
                hint="Dejar en 0 si no aplica garantia"
                
              />
          </div>
          <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Descripción
            </div>
            <q-input
              borderless
              clearable
              v-model="formData.description"
              class="form__inputsR mt-1"
              color="primary"
            />
          </div>
        </div>
      </Transition>
      <Transition name="horizontal">
        <div class="row w-full" style="height:83%; overflow:auto" v-if="step==1">
          
          <div class="col-md-6 col-12  row mt-1 px-2 md:px-12">
            <div class="col-12">
              <div class="text-subtitle2 text-black ">
                Maximo de horas de reserva
              </div>
              <q-input
                  dense
                  borderless
                  clearable
                  v-model="formData.maxTime"
                  class="form__inputsR mt-1"
                  autofocus
                  color="primary"
                  :rules="[ val => !(!val) ||  'Las horas maxima de reserva es necesaria']"
              />
            </div>
            <div class="col-12 mt-6">
              <div class="text-subtitle2 text-black">
                Horas disponibles:
              </div>
              <div class="row w-full mt-4">
                <div class="col-6  pr-2 md:pr-4">
                  <div class="text-body2 text-black" style="font-weight: medium;">
                    Desde:
                  </div>
                  <q-input v-model="formData.timeFrom" mask="time" :rules="['time']" dense
                    borderless
                    clearable
                    class="form__inputsR mt-1"
                    color="primary"
                  >
                    <template v-slot:append>
                      <q-icon name="eva-clock-outline" class="cursor-pointer">
                        <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                          <q-time v-model="formData.timeFrom">
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Aceptar" color="primary" flat />
                            </div>
                          </q-time>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>

                </div>
                <div class="col-6  pl-2 md:pl-4">
                  <div class="text-body2 text-black" style="font-weight: medium;">
                    Hasta:
                  </div>
                  <q-input v-model="formData.timeTo" mask="time" :rules="['time']" dense
                    borderless
                    clearable
                    class="form__inputsR mt-1"
                    color="primary"
                  >
                    <template v-slot:append>
                      <q-icon name="eva-clock-outline" class="cursor-pointer">
                        <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                          <q-time v-model="formData.timeTo">
                            <div class="row items-center justify-end">
                              <q-btn v-close-popup label="Aceptar" color="primary" flat />
                            </div>
                          </q-time>
                        </q-popup-proxy>
                      </q-icon>
                    </template>
                  </q-input>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
            <div class="text-subtitle2 text-black">
              Normas
            </div>
            <q-input
                dense
                borderless
                clearable
                type="textarea"
                v-model="formData.rules"
                class="form__inputsR mt-1"
                color="primary"
                :rules="[ val => val && val.length > 0 || 'Indica las reglas']"
              />
          </div>
          
        </div>
      </Transition>
      <div class="w-full flex flex-center" style="height:10%; overflow:hidden">
        <div class="w-full px-2 md:px-12 flex justify-center">
          <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="backButton()" v-if="step == 1" class="me-7"> 
            <div class="md:px-10 px-5 py-1" >
              Volver
            </div>
          </q-btn>
          <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="md:px-10 px-10 py-1" >
              Siguiente
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
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
.boxImgStore {
  border-radius: 0.8rem;
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  // border: 2px solid rgb(3, 156, 195) ;
  height: 7rem;
  width: 15%;
  margin: auto;
  box-shadow: 0px 0.1rem 1rem 0px rgba(0, 0, 0, 0.205);
  background-repeat: no-repeat;
  background-size: cover;
  background-color: #2d6fb5;
  transition: all 0.7s ease-in-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.03);
  }
  & img{
    height: 70%;
  }
}
@media (max-width: 780px) {
  .boxImgStore {
    height: 7rem;
    width: 32%;

  }
.form__inputsR{
  & .q-field__inner {

    padding: 0.1rem 1rem;
  }
}
}

</style>