<script setup>
import { ref, onMounted } from 'vue';
import transfer from '@/assets/img/util/transfer.webp'
import yape from '@/assets/img/util/yape.webp'
import cash from '@/assets/img/util/cash.webp'
import { useRoute, useRouter } from 'vue-router';
import { useQuotaStore } from '@/services/store/quota.store'
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import { Notify } from 'quasar';

const myLocale = {
  /* starting with Sunday */
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  firstDayOfWeek: 1, // 0-6, 0 - Sunday, 1 Monday, ...
  format24h: true,
  pluralDay: 'dias'
}

const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()
const ready = ref(false)
const step = ref(1)
const loading = ref(false)
const disable =  ref(true)
const quota = ref({})
const payFormData = ref({
  pay_method:0,
  amount:'',
  vaucher:null,
  reference:'',
  date:'',
  type:2
})

const payMethods = [
  {
    title: 'Transferencia Bancaria',
    img:transfer
  },
  {
    title: 'Yape',
    img:yape
  },
  {
    title: 'Pago en efectivo',
    img: cash
  }
]

const payData = [
  [],
  [
    {
      title:'N° de cuenta',
      value:'0000000000000'
    },
    {
      title:'Banco',
      value:'BCP'
    },
    {
      title:'Titular de la cuenta',
      value:'Juan Perez'
    },
  ],
  [
    {
      title:'N° de télefono',
      value:'997 245 369'
    },
    {
      title:'Titular de la cuenta',
      value:'Juan Perez'
    },
    {
      title:'QR',
      value:'https://upload.wikimedia.org/wikipedia/commons/d/d7/Commons_QR_code.png'
    },
  ],
  [
    {
      title:'Dirección de entrega',
      value:'Lobby edificio central, Horario de 8:00 - 15:00'
    },
  ],

]

const nextStep = () => {
  if(step.value == 3 || (step.value == 2 && payFormData.value.pay_method == 3)){
    createQuotaPay()
    return
  }

  if(step.value == 2){
    disable.value = true
  }
  step.value++


}
const stepBack = () => {
  if(step.value ==1){
    router.go(-1)
    return
  } 
  step.value--
}

const getBookingById = () => {
  quotaStore.getQuotaById(route.params.id || route.query.id)
  .then((response) => {
    quota.value = response.data
    ready.value = true
  })
  .catch((response) => {
    console.error(response)
    ready.value = true
  })
}

const activeClass = (event) => {

  try{
    document.querySelector('.payMethodItem.active').classList.remove('active')
  }catch{
  }
  disable.value = false
  event.target.closest('.payMethodItem').classList.add('active')
}
const onFileChange = () => {
  if(payFormData.value.reference) disable.value = false
}


const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
const createQuotaPay = () => {
  const dataForm = new FormData
  dataForm.append('amount', quota.value.amount)
  dataForm.append('vaucher', payFormData.value.vaucher)
  dataForm.append('reference', payFormData.value.reference)
  dataForm.append('pay_date', payFormData.value.date)
  dataForm.append('pay_method', payFormData.value.pay_method)
  dataForm.append('booking_id', quota.value.id)
  dataForm.append('type', 2)


  loading.value = true

  quotaStore.createQuotaPay(dataForm)
  .then((response) => {
    showNotify('positive', 'Pago creado con exito')
    setTimeout(() => {
      loading.value = false
      router.push('/client/quotas/pay/details/'+response.data.idPay)
    }, 1000);
  })
  .catch((response) => {
    loading.value = false
    showNotify('positive', 'Error al crear el pago')

  })
}
const formatAllToCopy = () => {
  let dataFormatted = ''
  try {
    payData[payFormData.value.pay_method].forEach(data => {
      if(data.title !='QR'){
        dataFormatted += (data.title !='Titular de la cuenta' ? data.value.replaceAll(' ', '') : data.value) +' '  
      }
    });
  } catch (error) {
    console.error('Error al copiar la data')
  }
  copyData(dataFormatted.trim())
}
const formatCopy = (texto) => {
  copyData(texto.replaceAll(' ', '').trim())
}
const copyData = (texto) => {
  const element = document.getElementById('textToPaste')
  const textArea = document.createElement('textarea');
  textArea.value = texto
  textArea.style.opacity = 0;

  element.appendChild(textArea);
  textArea.select();

  try {
    const success = document.execCommand('copy');
    showNotify('positive', 'Datos copiados con exito')
  } catch (err) {
    console.error(err.name, err.message);
  }
  finally{
    element.removeChild(textArea);
  }
}
onMounted(() => {
  getBookingById()
})
</script>
<template>
  <div class="h-full md:px-28 bg-white" >
    <q-form @submit="nextStep()" class="h-full ">
      <template v-if="ready">
        <section  class=" pt-2 px-3 flex justify-between items-center bg-primary header__container "
          style=" border-bottom-left-radius: 2rem; border-bottom-right-radius: 2rem; height: 10%; position: relative; z-index: 2;">
            <div class="flex items-center">
              <div class="flex items-center">
                <q-btn icon="eva-arrow-back-outline" unelevated color="white" flat size="1rem" round
                  @click="stepBack()" />
              </div>
              <div class="text-h6 text-bold text-white ml-2">
                {{ route.meta.pagTitle}}
              </div>
            </div>
        </section>
        <div class="md:px-20 md:mx-20 bg-stone-100" style="height: 68%;">
          <Transition name="horizontal">
            <div class="h-full px-4 md:px-0 "  v-if="step == 1">
              <div class="text-h6 text-bold text-black py-5 px-1">
                ¿Cómo vas a pagar?
              </div>
              <div v-for="(method, key) in payMethods" :key="key"  class="payMethodItem mb-5 py-2 px-3 flex justify-between items-center">
                  <div class="flex items center">
                    <div class="bg-white" style="border-radius: 0.5rem;">
                      <img :src="method.img" alt="" class="" style="width: 2rem; border-radius: 0.5rem; ">
                    </div>
                    <div class="text-subtitle1  ml-2">
                      {{ method.title }}
                    </div>
                  </div>
                  <div>
                    <q-radio v-model="payFormData.pay_method" :val="(key+1)"  @click="activeClass($event)"   />
                  </div>
              </div>
            </div>
          </Transition>
          <Transition name="horizontal">
            <div class="h-full md:pt-5" style="overflow: auto;" v-if="step == 2">
              <div v-if="payFormData.pay_method!==3">
                <div class="dataPayCard pt-6 pb-3 px-3 md:px-8 md:py-8"  style="transform: translateY(-0.4rem);">
                  <div class="pb-5 text-h6 text-bold text-black"> 
                    Paga tu reserva
                    <div class=" text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                      Asegúrate de pagar correctamente, utiliza los datos que te aparcen aca
                    </div>
                  </div>
                  <div v-for="(data, key) in payData[payFormData.pay_method]" :key="key" class=" mb-5 flex items-center justify-between" >
                    <div :class="{'w-full':data.title == 'QR'}">
                      <div class="text-body2 mb-1 text-grey-7">{{ data.title }}</div>
                      <img style="width: 8rem;" :src="data.value" alt="" v-if="data.title == 'QR'" class="mx-auto">
                      <div v-else style="font-size: 1.05rem;" class="text-black text-bold ">{{ data.value }}</div>
                    </div>
                    <div v-html="iconsApp.copyIcon" class="cursor-pointer" v-if="data.title != 'QR'" 
                    @click="data.title.includes('Titular') ? copyData(data.value) : formatCopy(data.value)"/>
                  </div>
                  <div class="flex flex-center mt-6 cursor-pointer">
                    <div v-html="iconsApp.copyIcon" />
                    <div class="ml-1 text-primary" @click="formatAllToCopy()" style="font-size: 1.02rem; font-weight: medium;">Copiar datos</div>
                  </div>
                </div>
              </div>
              <div v-else>
                <div class="dataPayCard pt-6 pb-3 px-3 md:px-8 md:py-8" style="transform: translateY(-0.4rem);">
                  <div class="pb-7 text-h6 text-bold text-black"> 
                    Paga tu reserva
                    <div class=" text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                      Dirigete a nuestra oficina, para finalizar tu reserva
                    </div>
                  </div>
                  <div class="text-center text-black text-moneyEfectivo" >
                    Dirigete a la siguiente ubicación de nuestra oficina para realizar el abono en efectivo:
                  </div>
                  <div class="md:px-8">
                    <div class="my-4 text-center text-grey-8 text-subtitle1 px-4 py-4 box-data" >
                      Av. Alfredo Benavides 430, Miraflores 15074.
                    </div>
                  </div>
                  <div class="mt-7 mb-4 text-center">
                    <div class="text-moneyEfectivo text-black">El codigo de tu reservación es:</div>
                    <div class="flex flex-center">
                      <div class="text-primary text-h5  mt-4 box-data pl-4 pr-3 py-3 flex items-center">
                        #00{{ quota.booking_number }}
                        <div class="ml-2">
                            <div v-html="iconsApp.copyIcon" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
          <Transition name="horizontal">
            <div class="h-full md:pt-5" style="" v-if="step == 3">
              <div class="dataPayCard p-5 pb-3 px-3 md:px-8 md:py-8" style="transform: translateY(-0.4rem);">
                <div class="pb-7 text-h6 text-bold text-black"> 
                  Confirma tu pago
                  <div class=" text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                    Completa el formulario
                  </div>
                </div>
                <div class=" row mt-1 px-1 md:px-12">
                  <div class="col-12 mt-0">
                    <div class="text-subtitle2 text-black">
                      Fecha de pago:
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-input v-model="payFormData.date" :rules="[val => !(!val) || 'Fecha es requerida']" dense
                          borderless clearable class="form__inputsPay mt-1" color="primary" accept=".jpg, image/*">
                          <template v-slot:append>
                            <q-icon name="eva-calendar-outline" class="cursor-pointer">
                              <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                <q-date mask="DD-MM-YYYY" v-model="payFormData.date"
                                  @update:model-value="getAvaibleBookingByDay"
                                  :navigation-min-year-month="moment().format('YYYY/MM')" :locale="myLocale">
                                  <div class="row items-center justify-end">
                                    <q-btn v-close-popup label="Aceptar" color="primary" flat />
                                  </div>
                                </q-date>
                              </q-popup-proxy>
                            </q-icon>
                          </template>
                        </q-input>

                    </div>
                  </div>
                  <div class="col-12 mt-2 ">
                    <div class="text-subtitle2 text-black ">
                      Referencia de pago
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-input
                        dense
                        borderless
                        clearable
                        v-model="payFormData.reference"
                        class="form__inputsPay mt-1"
                        :maxlength="12"
                        color="primary"
                        :rules="[ val => !(!val) ||  'La refrencia de pago es obligatoria']"
                      />
                    </div>
                  </div>                    
                  <div class="col-12 mt-2 mb-4">
                    <div class="text-subtitle2 text-black ">
                      Vaucher de pago
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-file v-model="payFormData.vaucher"  dense
                        borderless
                        clearable
                        class="form__inputsPay mt-1"
                        color="primary"
                        @update:model-value="onFileChange"
                      >
                        <template v-slot:append>
                          <q-icon name="eva-folder-add-outline" class="cursor-pointer">
                          </q-icon>
                        </template>
                        <template v-slot:selected>
                        <div class="row items-center q-gutter-x-sm">
                          <q-icon name="eva-checkmark-circle-2-outline" color="positive" size="sm" />
                          <div>Archivo subido</div>
                        </div>
                      </template>
                      </q-file>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
        <div class="md:px-20 md:mx-20 " style="height: 22%;">
          <div style="" class=" py-3 summarySection">
            <div class="mb-3 md:px-16 px-5">
              <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid lightgrey;">
                <div class="text-subtitle2 text-grey-8">Mes</div>
                <div class="text-subtitle1 text-bold text-black">{{ quota.month_label }}</div>
              </div>
              <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid lightgrey;">
                <div class="text-subtitle2 text-grey-8">Total</div>
                <div class="text-subtitle1 text-bold text-black">S/. {{ quota.amount }}</div>
              </div>
            </div>
            <div class="flex flex-center w-full">
              <q-btn color="primary" class="" style="width: 90%; border-radius: 0.5rem;" type="submit"
                :loading="loading" :disable="disable">
                <div class="py-1 md:py-2">
                  {{ 
                    step == 3 
                    ? 'Pagar reserva' 
                    : step == 2 && payFormData.pay_method == 3 
                    ? 'Confirmar pago'
                    : step == 2
                    ? 'Ya hice el pago' 
                    : 'Siguiente' }}
                </div>
              </q-btn>
            </div>
          </div>
        </div>
      </template>
      <div v-else class="flex flex-center  h-full">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

    </q-form>
    <div id="textToPaste" />
  </div>
</template>
<style lang="scss">
.summarySection{
  border: 2px solid lightgray;
  border-top-left-radius: 1rem; 
  border-top-right-radius: 1rem; 
}
.q-date__today{
  background: #0351824d;
  color: $primary
}
.box-data{
  background: #03518221;
  border-radius: 1rem;
  width: auto;
  font-weight: 600;
}
.text-moneyEfectivo{
  font-weight: bold; font-size: 1rem;
}
.dataPayCard{
    background: white;
    border-radius: 1.2rem;
}
.payMethodItem{
  border-radius: 0.6rem;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
  border:1px solid rgb(199, 199, 199);
  transition: all 0.5s ease-in-out;
  color:black;
  &.active{
    background: rgba(17, 104, 204, 0.925);
    color: white;
    border:1px solid rgba(17, 104, 204, 0.651);
    box-shadow: 0px 2px 6px 0px rgba(20, 61, 107, 0.966);
  }
  &:hover{
    transform: scale(1.01);
    background: rgba(17, 104, 204, 0.925);
    color: white;
    border:1px solid rgba(17, 104, 204, 0.651);

    box-shadow: 0px 2px 6px 0px rgba(20, 61, 107, 0.966);

  }
}
.form__inputsPay{
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}


@media (max-width: 780px) {
.form__inputsPay{
  & .q-field__inner {

    padding: 0.1rem 1rem;
  }
}
.dataPayCard{
  border-radius: 0;
  border-bottom-left-radius: 1.2rem;
  border-bottom-right-radius: 1.2rem;
}
.summarySection{
  border: 1px solid lightgray;
  border-top-left-radius: 1rem; 
  border-top-right-radius: 1rem; 
  box-shadow: 0px 0.2rem 1rem 0px rgb(155 155 155 / 53%);
}
}

</style>