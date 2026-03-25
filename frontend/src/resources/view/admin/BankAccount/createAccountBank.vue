<script setup>
import { onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar'
import { useRouter } from 'vue-router';
import { usePayMethodStore } from '@/services/store/payMethod.store';

const payMethodStore = usePayMethodStore()
const router = useRouter()
const loading = ref(false)
const step = ref(1)
const typeOfData = [
  { value: 1, name: 'texto' },
  // { value: 2, name: 'imagen' },
]

const formData = ref({
  name: '',
  dataList: [
    { 
      title: '', 
      data:'',
      type:{ value: 1, name: 'texto' },
    }
  ]
})

const addRule = () => {
  formData.value.dataList.push({ 
      title: '', 
      data:'',
      type:{ value: 1, name: 'texto' },
    })
}

const removeRule = (index) => {
  formData.value.dataList.splice(index, 1)
}
const backButton = () => {
  step.value--
}
const nextStep = () => {
  if (step.value == 1) {
    createPayMethod()
    return
  }

  step.value++
}
const createPayMethod = () => {
  loading.value = true

  payMethodStore.createPayMethod(formData.value)
    .then((response) => {
      if (response.code !== 200) throw response


      showNotify('positive', 'Metodo de pago agregado con exito')
      setTimeout(() => {
        loading.value = false
        router.go(-1)

      }, 1000)
    })
    .catch((response) => {
      loading.value = false
      showNotify('positive', 'Error al crear Metodo de pago')

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
})

</script>
<template>
  <div class="md:px-24 px-2 h-full" style="overflow: hidden; ">
    <div class="text-center text-black text-h5 text-bold  md:mb-8 mb-2 headerForm">
      Metodo de pago
    </div>
    <q-form @submit="nextStep()" class="formContent" style="overflow: auto;">

      <div class=" w-full h-full">
        <Transition name="horizontal">
          <div class="w-full" style="height:85%; overflow:hidden" v-if="step == 1">
            <div class="col-12 px-2 md:px-12 w-full h-full">
              <div class="row w-full" style="height:17%; overflow:hidden">
                <div class="col-md-6 col-12 mt-1 md:mt-0 px-0 md:px-0">
                  <div class="text-subtitle2 text-black">
                    Nombre 
                  </div>
                  <q-input placeholder="Ej: transferencia, Yape, Tarjeta" dense borderless clearable v-model="formData.name" class="form__inputsR mt-1" color="primary"
                    :rules="[val => val && val.length > 0 || 'Nombre de area es requerido']" />
                </div>
              </div>
              <div class="" style="height:83%;">
                <div class="row justify-between items-center bg-white " style="height: 15%;">
                  <div class="text-subtitle1 text-bold text-black">
                    Agregar lineas
                  </div>
                  <div>
                    <q-btn color="primary" icon="eva-plus-outline" @click="addRule" outline rounded dense class="px-3" />
                  </div>
                </div>
                <div style="height: 85%; overflow: auto;">
                  <div v-for="(data, index) in formData.dataList" :key="index"
                    class="row w-full relative-position md:mt-14 pb-8 mt-5" style="">
                    <q-btn v-if="formData.dataList.length > 1" icon="eva-trash-2-outline" color="negative" dense outline
                      round @click="removeRule(index)" class="absolute-top-right"
                      style="top: -15px; right: 5px; z-index: 10;" />

                    <div class="col-md-6 col-6 mt-1 pr-2">
                      <div class="text-subtitle2 text-black">
                        Campo
                      </div>
                      <q-input dense borderless clearable v-model="data.title" class="form__inputsR mt-1 bg-white"
                        color="primary" :rules="[val => !!val || 'El título es requerido']" />
                    </div>
                    <div class="col-md-6 col-6 mt-1 pl-2">
                      <div class="text-subtitle2 text-black">
                       Tipo de valor
                      </div>
                      <q-select class="form__inputsR mt-1 bg-white" dense v-model="data.type"
                      :options="typeOfData" option-label="name" option-value="value" borderless />
                    </div>
                    <div class="col-12 mt-1">
                      <div class="text-subtitle2 text-black">
                       Valor
                      </div>
                      <q-input dense borderless clearable v-model="data.data" class="form__inputsR mt-1 bg-white"
                        color="primary" :rules="[val => !!val || 'Este valor es obligatorio']" />
                    </div>

                  </div>
                </div>
              </div>

            </div>
          </div>
        </Transition>
        <div class="w-full flex flex-center pt-1" style="height:10%; overflow:hidden">
          <div class="w-full px-2 md:px-12 flex justify-center">
            <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="backButton()" v-if="step > 1" class="me-7">
              <div class="md:px-10 px-5 py-1">
                Volver
              </div>
            </q-btn>
            <q-btn color="primary p" style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="md:px-10 px-10 py-1">
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
.headerForm {
  height: 7%;
}

.formContent {
  height: 93%;
}

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 10px 1rem;
  }
}
@media (max-width: 780px) {
  .boxImgStore {
    height: 7rem;
    width: 32%;

  }

  .form__inputsR {
    & .q-field__inner {

      padding: 0.1rem 1rem;
    }
  }
}
</style>