<script setup>
import { onMounted, ref, inject, watch, onBeforeUnmount } from 'vue';
import { Notify } from 'quasar'
import { useRouter, useRoute } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';
import { useReserveStore } from '@/services/store/reserve.store';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
moment.locale('es', {
  weekdays: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  weekdaysShort: 'Dom_Lun_Mar_Mie_Jue_Vie_Sab'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})
const myLocale = {
  /* starting with Sunday */
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'DO_LU_MA_MI_JU_VI_SA'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  // 0-6, 0 - Sunday, 1 Monday, ...
  format24h: true,
  pluralDay: 'dias'
}
const rulesModal = ref(false)
const comunAreaStore = useComunAreaStore()
const reserveStore = useReserveStore()
const emitter = inject('emitter')
const comunAreas = ref([])
const selectedComunArea = ref({})
const router = useRouter()
const disabledTime = ref(true)
const ready = ref(false)
const loading = ref(false)
const step = ref(4)
const selectedInterval = ref({})
const transitionName = ref('slide-next');
const formData = ref({
  date: '2026/03/25',
  time_from: '08:00',
  time_to: '10:00',
  note: '',
  is_exclusive: false,
  terms_accept: false,
  multa_accept: false
})
const tapActive = ref(
  moment().format('D') !== moment(formData.value.date).format('D')
    ? 'ma'
    : moment().format('H') < 12
      ? 'ma' : moment().format('H') >= 12 && moment().format('H') < 18
        ? 'ta' : 'no')
const intervalHorarys = ref({
  ma: [],
  ta: [],
  no: []
})
const selectedPayData = ref({})
const selectArea = (id) => {
  selectedComunArea.value = comunAreas.value.find((area) => area.id == id)
  nextStep()
}

const backButton = () => {
  if (step.value == 2) {
    cleanForm()
    visibleBackButton(true)
    emitter.emit('isReserve',
      {
        visible: false,
        data: {
          step: 1,
          icon: '',
          name: ''
        }
      })
  }
  step.value--

  emitter.emit('pagTitle', 'Selecciona area común')
}

const nextStep = () => {
  if (!validateStepForm()) {
    return
  }

  if (step.value == 3 && selectedComunArea.value.type == 1) {
    createReserve()
    return
  } else {
    rulesModal.value = false
  }

  if(step.value = 4)
  emitter.emit('pagTitle', '')
  step.value++
  emitter.emit('isReserve',
    {
      visible: true,
      data: {
        step: step.value,
        icon: selectedComunArea.value.icon,
        name: selectedComunArea.value.name
      }
    })
  visibleBackButton(false)
}
const cleanForm = () => {
  formData.value = {
    date: '',
    time_from: '',
    time_to: '',
    note: '',
    is_exclusive: false,
  }
  disabledTime.value = true
}
const getComunsArea = () => {
  emitter.emit('pagTitle', 'Selecciona area común')
  comunAreaStore.getAllComunAreas()
    .then((response) => {
      if (response.code !== 200) throw response
      comunAreas.value = response.data
      // -- borrar luego --
      selectedComunArea.value = comunAreas.value.find((area) => area.id == 2)
      getAvaibleBookingByDay()
      visibleBackButton(false)
      setTimeout(() => {
        // -- borrar luego --
        emitter.emit('isReserve',
          {
            visible: true,
            data: {
              step: step.value,
              icon: selectedComunArea.value.icon,
              name: selectedComunArea.value.name
            }
          })
        ready.value = true
      }, 100)
    })
    .catch((response) => {
      console.log(response)
      showNotify('negative', 'Error al obtener areas comunes')
    })
}

const validateStepForm = () => {
  if (step.value == 2) {
    !formData.value.date ? showNotify('negative', 'Debe seleccionar la fecha de la reserva') : ''
    return formData.value.date ? true : false
  }
  if (step.value == 3) {
    !formData.value.terms_accept || !formData.value.multa_accept ? showNotify('negative', 'Debe aceptar los terminos y pagos de multas') : ''
    return formData.value.terms_accept && formData.value.multa_accept ? true : false
  }
  if (step.value == 4) {
    !payFormData.value.pay_method || payFormData.value.pay_method == 0 
    ? showNotify('negative', 'Debe seleccionar el metodo de pago') : ''
    return payFormData.value.pay_method && payFormData.value.pay_method != 0 ? true : false
  }
  return true
}
const getAvaibleBookingByDay = () => {

  const data = {
    idArea: selectedComunArea.value.id,
    date: formData.value.date
  }

  reserveStore.getAvailableReserveInDayByArea(data)
    .then((response) => {
      disabledTime.value = false
      intervalHorarys.value = response.data.blocks

    })
}
const optionsFn = (date) => {
  const isFutureOrToday = date >= moment().format('YYYY/MM/DD');

  const dayOfWeek = moment(date, 'YYYY/MM/DD').day();
  const isWeekday = dayOfWeek !== 0 && dayOfWeek !== 6;

  // return isFutureOrToday && isWeekday;
  return isFutureOrToday
}


const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const createReserve = () => {
  formData.value.comun_area = selectedComunArea.value.id
  formData.value.amount = selectedComunArea.value.price + selectedComunArea.value.warranty_price
  formData.value.exclusive = formData.value.is_exclusive ? 1 : 0;

  loading.value = true
  reserveStore.createReserve(formData.value)
    .then((response) => {
      setTimeout(() => {
        loading.value = false
        showNotify('positive', !response.data.toPay ? 'Reserva realizada con exito' : 'Pre-reservación realizada')

        if (!response.data.toPay) {
          router.push('/client/reserves/confirm-reserve/' + response.data.id)
          return
        }

      }, 2000);

    })
    .catch((response) => {
      console.log(response)
      setTimeout(() => {
        loading.value = false
        showNotify('negative', 'Error al realizar reserva')

      }, 2000);

    })
}
const mediaUrl = import.meta.env.VITE_LARAVEL_MEDIA_URL

const visibleBackButton = (visible) => {
  const element = document.querySelector('.backButton');
  const tope = document.querySelector('.page_continerContent');
  element.style.display = visible ? 'flex' : 'none'
  // tope.style.height = visible ? '90%' : '100%'

}
const changeTap = (tab) => {
  tapActive.value = tab;
}
const setColor = (status) => {
  return status == 'Disponible'
    ? 'primary'
    : status == 'Últimos'
      ? 'terciary'
      : ''
}
const setSelectHour = (index) => {
  selectedInterval.value = intervalHorarys.value[tapActive.value][index];
  formData.value.time_to = selectedInterval.value.time_to
  formData.value.time_from = selectedInterval.value.time_from
  selectedInterval.value.id = tapActive.value + '-' + index;
}
const openRuleModal = (status) => {
  if (!formData.value.time_from) {
    showNotify('negative', 'Selecciona un intervalo de tiempo para tu reserva')
    return
  }
  rulesModal.value = status
}

//payment 

const payFormData = ref({
  pay_method: 0,
  amount: '',
  vaucher: null,
  reference: '',
  date: '',
  booking_id: null,
  type: 2
})


const payMethods = [
  
  {
    title: 'Transferencia bancaria',
    id: 1
  },
  {
    title: 'Yape / Plin',
    id: 2
  },
  {
    title: 'Tarjeta (crédito / débito)',
    id: 3
  },

]

const payData = [
  [],
  [
    {
      title: 'Banco',
      value: 'BCP'
    },
    {
      title: 'Cuenta corriente',
      value: '0000000000000'
    },
    {
      title:'CCI',
      value:'0000000000'
    }
  ],
  [
    {
      title: 'N° de télefono',
      value: '997 245 369'
    },
    {
      title: 'Titular de la cuenta',
      value: 'Juan Perez'
    },
    {
      title: 'QR',
      value: 'https://upload.wikimedia.org/wikipedia/commons/d/d7/Commons_QR_code.png'
    },
  ],
]
const setPayData = (e) => {
  selectedPayData.value = payData[e]
}
const formatAllToCopy = () => {
  let dataFormatted = ''
  try {
    payData[payFormData.value.pay_method].forEach(data => {
      if (data.title != 'QR') {
        dataFormatted += (data.title != 'Titular de la cuenta' ? data.value.replaceAll(' ', '') : data.value) + ' '
      }
    });
  } catch (error) {
    console.log('Error al copiar la data')
  }
  copyData(dataFormatted.trim())
}
const formatCopy = (texto) => {
  copyData(texto.replaceAll(' ', '').trim())
}
const copyData = (texto) => {
  const element = document.getElementById('textToPasteData')
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
  finally {
    element.removeChild(textArea);
  }
}
const pegarTexto = async () => {  
  if (!navigator.clipboard) {
    console.warn('La API del portapapeles no está disponible. Asegúrate de usar HTTPS o localhost.')
    return
  }
  try {
    // Leemos el texto del portapapeles del sistema
    const textoDelPortapapeles = await navigator.clipboard.readText()
    
    // Lo asignamos al v-model del segundo input
    payFormData.value.reference = textoDelPortapapeles
  } catch (err) {
    // Esto se ejecutará si el usuario deniega el permiso o el navegador no lo soporta
    console.error('Error al intentar pegar: ', err)
  }
}
onMounted(() => {
  getComunsArea()
})

onBeforeUnmount(() => {
  emitter.emit('isReserve',
    {
      visible: false,
      data: {
        step: 1,
        icon: '',
        name: ''
      }
    })
});
watch(step,
  (toDepth, fromDepth) => {
    transitionName.value = toDepth > fromDepth ? 'slide-next' : 'slide-prev';
  }
);
</script>
<template>
  <div class=" h-full " style="overflow: hidden; position: relative;">
    <div class="h-full md:px-20 md:mx-16 " v-if="ready">
      <q-form @submit="nextStep()" class="h-full ">
        <Transition :name="transitionName">
          <div class="px-2 form-step row" v-if="step == 1">
            <div class="col-md-3 col-4 px-2 md:px-5 my-3" v-for="comunArea in comunAreas" :key="comunArea.id">
              <div class="boxContentV2 ">
                <div class="boxItem_v2  md:px-6 " @click="selectArea(comunArea.id)">
                  <div class="flex justify-center items-center h-full w-full ">
                    <img :src="mediaUrl + '/images/icons/' + comunArea.icon + '.svg'" alt="" style="height:100%">
                  </div>
                </div>
                <div class="text-center mt-0 pt-1 px-2 text-title-squadV2 text-white text-ellipsis ellipsis "
                  style="background: #2d5eaa; height: 27%;">
                  {{ comunArea.name }}
                </div>
              </div>
            </div>
          </div>
        </Transition>
        <Transition :name="transitionName">
          <div class="h-full form-step" style="overflow: hidden;" v-if="step > 1">
            <div class=" w-full h-full ">
              <div class="w-full  pt-4 pb-2 px-4" style="height:15%">
                <div class="text-center text-[#5571b7] font-bold text-lg md:text-xl mb-2 mt-0 " v-if="step > 3">
                  <div class="font-bold text-2xl">PAGAR Y CONFIRMAR</div>
                </div>
                <div class="relative flex justify-between items-center px-3">
                  <div class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5/6 h-1.5 bg-[#d9dee8] z-0"></div>
                  <div v-for="n in 4" :key="n"
                    class="relative z-10 w-9 h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm md:text-base transition-colors duration-300"
                    :class="{
                      'bg-[#79b5a8] text-white': n < step,      /* Completado: Verde/Teal */
                      'bg-[#5571b7] text-white': n === step,    /* Actual: Azul oscuro */
                      'bg-[#d9dee8] text-[#202c44]': n > step   /* Futuro: Gris claro */
                    }">
                    {{ n }}
                  </div>
                </div>

                <div class="text-center text-[#5571b7] font-bold text-lg md:text-xl mt-3 mb-4" v-if="step < 4">
                  <div class="font-bold text-2xl" v-if="step === 1">Selecciona área común</div>
                  <div class="font-bold text-2xl" v-else-if="step === 2">Fecha de reserva</div>
                  <div class="font-bold text-2xl" v-else-if="step === 3">Selecciona la hora</div>

                </div>
              </div>
              <div :style="{ height: step >= 4 ? '76%' : '70%' }" style=" overflow: auto;" class="pb-5">
                <div class="row w-full pt-2">
                  <template v-if="step == 2">
                    <div class="flex flex-center w-full q-px-md">
                      <q-date color="tealedf" v-model="formData.date" minimal class="w-full calendarReserve"
                        :options="optionsFn" @update:model-value="getAvaibleBookingByDay" text-color="primary"
                        :navigation-min-year-month="moment().format('YYYY/MM')" :locale="myLocale">
                      </q-date>
                      <div class="w-full px-5">
                        <div class="bg-primary mt-4 py-2 w-full textInfoContainer">
                          <div class="text-white dateInfoTitle text-center">Fecha seleccionada:</div>
                          <div class="text-white dateInfoContent text-center">
                            {{ formData.date ? moment(formData.date).format('dddd DD [de] MMMM [del] YYYY') : '-----' }}
                          </div>
                        </div>
                      </div>
                    </div>

                  </template>
                  <template v-if="step == 3">
                    <div class="col-12 col-md-6 row md:px-5 px-4">
                      <div class="selectedDateBlock flex  items-center justify-between px-4 w-full py-2">
                        <div>
                          <div class="text-dateBlockTitle">Fecha elegida</div>
                          <div class="text-primary text-bold text-dateBlock">
                            {{ formData.date ? moment(formData.date).format('dddd DD') : '-----' }}
                            - {{ selectedComunArea.max_time_reserve == 1
                              ? moment.duration(selectedComunArea.max_time_reserve, 'hours').asMinutes() + ' min'
                              : selectedComunArea.max_time_reserve + ' hrs'
                            }}
                          </div>
                        </div>
                        <div>
                          <q-btn outline color="tealedf" rounded no-caps class="backFecha" @click="backButton()">
                            <div class="text-bold text-sm">
                              Cambiar fecha
                            </div>
                          </q-btn>
                        </div>
                      </div>
                      <div class="w-full">
                        <div class="tabBloque flex flex-center w-full pt-5">
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'ma' }" @click="changeTap('ma')">Mañana</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'ta' }" @click="changeTap('ta')">Tarde</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'no' }" @click="changeTap('no')">Noche</div>
                        </div>
                        <div class="mt-2 blockHoursContent  py-2 px-3">
                          <template v-if="intervalHorarys[tapActive].length > 0">
                            <div class="flex items-center justify-between blockHoursContent__item my-2 px-4 py-2"
                              v-for="(interval, index) in intervalHorarys[tapActive]"
                              :class="{ 'activeHour': selectedInterval?.id == (tapActive + '-' + index) }" :key="index"
                              @click="setSelectHour(index)">
                              <div class="text_interval pt-1">{{ interval.time_from }} - {{
                                interval.time_to }}</div>
                              <div>
                                <q-chip :color="selectedInterval?.id !== index ? setColor(interval.status) : 'tealedf'"
                                  text-color="white" size="0.8rem">
                                  <div style="font-size: 0.7rem;" class="">
                                    {{ selectedInterval?.id !== index ? interval.status : 'Seleccionado' }}
                                  </div>
                                </q-chip>
                              </div>
                            </div>
                          </template>
                          <template v-else>
                            <div class="flex flex-center column my-2 px-4 py-1">
                              <q-icon name="eva-clock-outline" color="grey-6" size="3rem" />
                              <div class="text-bold mt-1 text-grey-6">
                                No hay horarios disponibles para la reserva
                              </div>
                            </div>
                          </template>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template v-if="step == 4">
                    <div class="col-12 col-md-6 row md:px-5 px-4 mt-1">
                      <div class="w-full">
                        <div class="text-xl font-bold pl-2">
                          Resumen de reserva y pago
                        </div>
                        <div class="mt-2 blockHoursContent  py-2 px-3">
                          <div class="text-confirmSubtitleArea">
                            Servicio
                          </div>
                          <div class="text-confirmTitleArea ">
                            {{ selectedComunArea.name }} - Pacifik
                          </div>
                          <div class="text-confirmSubtitleArea mt-3">
                            Fecha y hora
                          </div>
                          <div class="text-grey-10 text-confirmDate">
                            {{ formData.date ? moment(formData.date).format('ddd DD') : '-----' }}
                            - {{ formData.time_from ? moment(formData.date + ' ' + formData.time_from).format('hh:mm A')
                              : '**:** **' }} - {{ selectedComunArea.max_time_reserve == 1
                              ? moment.duration(selectedComunArea.max_time_reserve, 'hours').asMinutes() + ' min'
                              : selectedComunArea.max_time_reserve + ' hrs'
                            }}
                          </div>
                        </div>
                        <div class="rulesContainer mt-4 px-2 pt-2 pb-1">
                          <div class="px-2 rulesContainer__Title">
                            Método de pago
                          </div>
                          <div>
                            <div v-for="method in payMethods" :key="method.id"
                              :class="{'activeMethodContainer': payFormData.pay_method == method.id}"
                              class="flex items-center justify-between selectMethodItem mb-2 mt-3 py-2 px-3">
                              <q-radio
                              color="tealedf"
                                :class="{'activeMethod': payFormData.pay_method == method.id}"
                                class="item_method-radio" 
                                v-model="payFormData.pay_method"
                                checked-icon="eva-checkmark-circle-outline" 
                                :val="method.id" :label="method.title" @update:model-value="setPayData" />
                            </div>
                          </div>
                        </div>
                        <div class="selectedDateBlock mt-4 px-3 w-full py-2">
                          <div class="text-lg font-bold ">
                            Resumen de pago
                          </div>
                          <div class="flex items-center justify-between w-full">
                            <div class="text__amountItem">Reserva ({{ selectedComunArea.max_time_reserve == 1
                              ? moment.duration(selectedComunArea.max_time_reserve, 'hours').asMinutes() + ' min'
                              : selectedComunArea.max_time_reserve + ' hrs' }})</div>
                            <div class="text__amountItem">S/ {{ selectedComunArea.price.toFixed(2) }}</div>
                          </div>
                          <div class="flex items-center justify-between w-full">
                            <div class="text__amountItem">Garantia</div>
                            <div class="text__amountItem">S/ {{ selectedComunArea.warranty_price.toFixed(2) }}</div>
                          </div>
                          <div class="flex items-center justify-between w-full mt-1 "
                            style="border-top: 2px solid #8b8e9446;">
                            <div class="text__amountTotal">Total</div>
                            <div class="text__amountTotal">
                              S/ {{ (selectedComunArea.price + selectedComunArea.warranty_price).toFixed(2) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template v-if="step == 5">
                    <div class="col-12 col-md-6 row md:px-5 px-4 mt-1">
                      <div class="w-full">
                        <div class="text-lg font-bold pl-2">
                          Pago
                        </div>
                        <div class="text-caption font-medium pl-2 text-grey-7">
                          Método seleccionado: 
                          {{ payMethods.find(method => method.id == payFormData.pay_method).title }}
                        </div>
                        <div class="font-medium pl-2 mt-3 " style="font-size:0.9rem">
                          Datos de cuenta a pagar
                        </div>
                        <div class="selectedDateBlock mt-1 px-3 w-full py-2">
                          <div v-for="(line, index) in selectedPayData" :key="index" class=" my-1 flex items-center justify-between">
                            <div class="flex items-center " :class="{ 'w-full': line.title == 'QR' }">
                              <div class="text-md text-grey-10 ">{{ line.title }}: </div>
                              <img style="width: 8rem;" :src="line.value" alt="" v-if="line.title == 'QR'" class="mx-auto">
                              <div v-else class="text-md text-grey-10 ml-1">{{ line.value }}</div>
                            </div>
                            <div v-html="iconsApp.copyIcon" class="cursor-pointer" v-if="line.title != 'QR'"
                              @click="line.title.includes('Titular') ? copyData(line.value) : formatCopy(line.value)" />
                          </div>
                        </div>
                        <div class=" row mt-2 md:px-12">
                          <div class="col-12 mt-0">
                            <div class=" md:pr-4">
                              <q-input color="tealedf" label="Fecha de pago" v-model="payFormData.date" :rules="[val => !(!val) || 'Fecha es requerida']" dense
                                borderless clearable class="form__inputsReverse mt-1"  accept=".jpg, image/*">
                                <template v-slot:append>
                                  <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                      <q-date mask="DD-MM-YYYY" v-model="payFormData.date"
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
                          <div class="col-12 mt-0 ">
                            <div class=" md:pr-4">
                              <q-input 
                                color="tealedf"
                                label="Referencia de pago" 
                                dense borderless clearable v-model="payFormData.reference" 
                                class="form__inputsReverse mt-0"
                                :maxlength="12"
                                :rules="[val => !(!val) || 'La refrencia de pago es obligatoria']">

                                  <template v-slot:append>
                                    <q-btn 
                                      color="tealedf" 
                                      size="0.1rem" outline style="padding:3px 6px" 
                                      no-caps
                                      @click="pegarTexto()"
                                    >
                                      <div class="text-xs">
                                        Pegar
                                      </div>
                                    </q-btn>
                                  </template>
                                </q-input>
                            </div>
                          </div>

                        </div>
                        <div></div>

                        <div class="selectedDateBlock mt-0 px-3 w-full py-2">
                          <div class=" flex flex-center column">
                            <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
                            <div class="text-center"> 
                              <div class="text-grey-7 font-medium">
                                Sube tu comprobante de pago
                              </div>
                              <div class="text-grey-6 font-medium">
                                Pulsa o haz click aqui para carga tu archivo
                              </div>
                            </div>
                            
                          </div>
                          <div></div>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
              <div :style="{ height: step >= 4 ? '9%' : '15%' }" class="buttonSection">
                <div class="row py-4 ">
                  <template v-if="step >= 4">
                    <div class="col-4 flex flex-center ">
                      <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                        @click="backButton()">
                        <div class="py-0 md:py-0">
                          Volver
                        </div>
                      </q-btn>
                    </div>
                    <div class="col-8 flex flex-center">
                      <q-btn outline color="primary" unelevated no-caps class=""
                        style="width: 95%; border-radius: 3rem;" type="submit" :loading="loading">
                        <div class="py-0 md:py-0" style="font-weight: 500;">
                          Aceptar y continuar
                        </div>
                      </q-btn>
                    </div>
                  </template>
                  <template v-if="step == 2">
                    <div class="col-12 flex flex-center">
                      <q-btn color="tealedf" unelevated="" class="" style="width: 90%; border-radius: 2rem;"
                        type="submit" :loading="loading">
                        <div class="flex w-full flex-center">
                          <div class="py-2 md:py-1 font-bold mr-2" style="font-size:0.95rem">
                            Elegir horario
                          </div>
                          <div class="flex flex-center"
                            style="height:1.7rem; width:1.7rem; border:2px solid white; border-radius:50%">
                            <q-icon name="eva-arrow-forward-outline" size="1rem" />
                          </div>
                        </div>
                      </q-btn>
                    </div>
                  </template>
                  <template v-if="step == 3">
                    <div class="col-12 flex flex-center px-5">
                      <div class="selectedDateBlock flex  items-center justify-between px-4 w-full py-2">
                        <div>
                          <div class="text-dateBlockTitle">Reserva</div>
                          <div class=" text-bold text-dateBlockBottom">
                            {{ formData.date ? moment(formData.date).format('ddd DD') : '-----' }}
                            - {{ formData.time_from ? moment(formData.date + ' ' + formData.time_from).format('hh:mm A')
                              : '**:** **' }}
                          </div>
                        </div>
                        <div>
                          <q-btn color="primary" unelevated rounded no-caps class="" @click="openRuleModal(true)">
                            <div class="text-bold text-sm">
                              Confirmar
                            </div>
                          </q-btn>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </div>

        </Transition>
        <div>
          <Transition :name="transitionName">
            <div class="h-full rulesModal px-3" style="overflow: hidden;" v-if="rulesModal">
              <div class="pb-4" style="overflow:auto; height:91%">
                <div class="text-center my-2 font-bold text-2xl text-primary">INSTRUCIONES</div>
                <div class="importantInfo__reserve py-2 pl-3 pr-1">
                  <div class="text-importantInfo text-grey-7">Importante</div>
                  <div class="text-importantInfo">
                    Para usar el área reservada debes leer y aceptar las normas e instrucciones. El incumplimiento puede
                    generar
                    multas y suspensión del uso del área
                  </div>
                </div>
                <div class="rulesContainer mt-2 px-2 pt-2 pb-1">
                  <div class="px-2 rulesContainer__Title">
                    Reglas de uso - {{ selectedComunArea.name }}
                  </div>
                  <div class="rulesContainer__Subtitle text-grey-7 px-2">
                    Seguridad - higiene y convivencia
                  </div>
                  <div class="flex items-center ruleDetailContainer my-2 py-2 px-3"
                    v-for="(rule, index) in selectedComunArea.rules_area" :key="rule.id">
                    <div class="ruleDetailContainer__index flex flex-center"
                      :class="{ 'bg-negative': rule.severity == 2, 'bg-tealedf': rule.severity == 1 }">
                      {{ index + 1 }}
                    </div>
                    <div class="ml-2 " style="font-size:0.82rem">
                      {{ rule.title }}
                    </div>
                  </div>
                </div>
                <div class="rulesContainer mt-2 px-2 pt-2 pb-1">
                  <div class="px-2 rulesContainer__Title">
                    Sanciones y multas
                  </div>
                  <div class="rulesContainer__Subtitle text-grey-7 px-2">
                    Monto referenciales (editables según reglamento interno)
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-1 py-1 px-3">
                    <div class="ml-1 font-bold" style="font-size:0.78rem">
                      Infracción
                    </div>
                    <div class="mr-1 font-bold" style="font-size:0.78rem">
                      Multa
                    </div>
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-2 py-1 px-3">
                    <div class="ml-1 font-bold" style="font-size:0.78rem">
                      Infracción #1
                    </div>
                    <div class="mr-1 font-bold text-negative" style="font-size:0.78rem">
                      S/ 80.00
                    </div>
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-1 py-1 px-3">
                    <div class="ml-1 font-bold" style="font-size:0.78rem">
                      Infracción #2
                    </div>
                    <div class="mr-1 font-bold text-negative" style="font-size:0.78rem">
                      S/ 120.00
                    </div>
                  </div>
                </div>
                <div class="importantInfo__reserve py-2 pl-1  pr-1 mt-2">
                  <div class="px-3 rulesContainer__Title">
                    Aceptación de términos e instrucciones
                  </div>
                  <div class="px-2">
                    <div class="mt-1">
                      <q-checkbox v-model="formData.terms_accept" class="check__accept"
                        checked-icon="eva-checkmark-circle-outline">
                        <div class="ml-2 accept_text">
                          {{ 'He leido y acepto las normas de uso del area ' + selectedComunArea.name }}
                        </div>
                      </q-checkbox>

                    </div>
                    <div class="mt-1">
                      <q-checkbox v-model="formData.multa_accept" class="check__accept"
                        checked-icon="eva-checkmark-circle-outline">
                        <div class="ml-2 accept_text ">
                          Acepto las sanciones y cobros por incumplimiento
                        </div>
                      </q-checkbox>

                    </div>
                    <div class="accept_text text-grey-7 px-2 mt-1">
                      * Debes marcar ambas casillas para continuar con la reserva
                    </div>
                  </div>
                </div>
              </div>
              <div class="row py-0 " style="height: 9%;">
                <div class="col-4 flex flex-center ">
                  <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    @click="rulesModal = false">
                    <div class="py-0 md:py-0">
                      Volver
                    </div>
                  </q-btn>
                </div>
                <div class="col-8 flex flex-center">
                  <q-btn outline color="primary" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    type="submit" :loading="loading">
                    <div class="py-0 md:py-0" style="font-weight: 500;">
                      Aceptar y continuar
                    </div>
                  </q-btn>
                </div>
              </div>
            </div>

          </Transition>
        </div>
      </q-form>
      <div id="textToPasteData" />
    </div>
    <div v-else class="flex flex-center py-24 w-full">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss">

.text__amountItem {
  font-size: 1rem;
  font-weight: 500;
  margin-bottom: 0.1rem;
}

.text__amountTotal {
  font-size: 1.16rem;
  font-weight: 500;
  color: #4d6bb4;
  margin-bottom: 0.1rem;
}

.item_method-radio {
  width: 100%;
  & .q-radio__inner {
    width: 1rem;
    min-width: 1rem;
    height: 1rem;
    margin-right: 0.5rem;
  }
  & .q-radio__label{
    width: 100%;
  }
  & .q-radio__bg {
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }

}

.selectMethodItem {
  border: 2px solid lightgrey;
  border-radius: 8rem;
  transition: all 0.2s ease-in;
}
.activeMethodContainer{
  border: 2px solid #72b9af;
  background: #f0f1f6;
}
.text-confirmTitleArea {
  font-size: 1.4rem;
  color: #4d6bb4;
  font-weight: bolder;
}

.text-confirmDate {
  font-size: 1.2rem;
  font-weight: 500;
}

.text-confirmSubtitleArea {
  font-size: 0.97rem;
  color: rgb(139, 139, 139);
  font-weight: 500;
}

.accept_text {
  font-size: 0.7rem;
  font-weight: 500;
}

.check__accept {
  & .q-checkbox__inner {
    min-width: 1rem;
    width: 1rem;
    height: 1rem;
  }

  & .q-checkbox__bg {
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
  }
}

.text-importantInfo {
  font-size: 0.75rem;
  font-weight: 500;
}

.ruleDetailContainer {
  background: #f0f1f6;
  border: 2px solid lightgrey;
  border-radius: 8rem;

  &__index {
    height: 1.4rem;
    width: 1.4rem;
    border-radius: 50%;
    color: white;
    font-size: 0.8rem;
  }
}

.importantInfo__reserve {
  background: #f0f1f6;
  border: 2px solid lightgrey;

  border-radius: 0.8rem;
}

.rulesContainer {
  border: 2px solid lightgrey;
  border-radius: 0.8rem;

  &__Title {
    font-size: 1.1rem;
    font-weight: bold;
  }

  &__Subtitle {
    font-size: 0.76rem;
    font-weight: 500;
  }

}

.rulesModal {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
}

.text-title-squadV2 {
  color: #1e5f96;
  font-weight: 500;
  font-size: 0.9rem;
}

.text_interval {
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.2s ease-in;
}

.blockHoursContent {
  border: 2px solid lightgrey;
  border-radius: 0.7rem;

  &__item {
    border: 2px solid lightgray;
    border-radius: 0.8rem;
    transition: all 0.2s ease-in;

    &.activeHour {
      border: 2px solid #72b9af;
      background: #f0f1f6;

      & .text_interval {
        color: #006396;
      }
    }
  }

}

.tabBloque {
  &__item {
    padding: 0.3rem 0.8rem;
    background: white;
    border: 2px solid lightgray;
    border-radius: 1.5rem;
    font-size: 0.8rem;
    transition: all 0.2s ease-in-out;

    &.active {
      color: white;
      background: $primary;
      border: 2px solid $primary;
    }
  }
}

.backFecha {
  background: white !important;
}

.selectedDateBlock {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;

  & .text-dateBlockTitle {
    font-size: 0.8rem;
  }

  & .text-dateBlock {
    font-size: 1.2rem;
  }
}

.text-dateBlockBottom {
  font-size: 1rem;
}

.dateInfoContent {
  font-weight: 600;
  font-size: 1.3rem;
}

.textInfoContainer {
  border-radius: 0.7rem;
}

.dateInfoTitle {
  font-weight: 500;
  font-size: 0.96rem;
}


.form-step {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
}

.calendarReserve {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;

  &.q-date {
    box-shadow: none;
  }

  & .q-date__calendar-weekdays .q-date__calendar-item {
    opacity: 1 !important;
    margin-top: 0.7rem;
    margin-bottom: 0.7rem;

    & div {
      background: #5571b7;
      color: white;
      border-radius: 50rem;
      width: 2rem !important;
      height: 2rem !important;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;

    }

    &:last-child div,
    &:first-child div {

      color: white;
      background-color: #79b5a8;
    }
  }

  & .q-date__calendar-days .q-date__calendar-item {

    opacity: 0.5;

    & button {
      width: 40px;
      height: 40px;
    }

    &.q-date__calendar-item--out div {
      color: $primary;
      font-size: 1.1rem;
      font-weight: 600 !important;
    }

    &.q-date__calendar-item--in {
      opacity: 1 !important;

      & .block {
        color: $primary;
        font-size: 1.1rem;
        font-weight: 600;
      }

      &.q-date__calendar-item--in:nth-child(7n + 1) .block,
      &.q-date__calendar-item--in:nth-child(7n) .block {
        color: #79b5a8
          /* Tu color dorado */
      }

      & .q-btn--unelevated .block {
        color: white !important;
      }
    }
  }

}

.q-date__navigation,
.q-time__clock-position {
  color: black;
}

.q-time__clock-position--active {
  color: white;
}

.q-date__calendar-item {
  color: black;
}



.md\:order-3 {
  order: 3
}

.text-body2x {
  font-size: 0.9rem;
}

.headerSection {
  background: #0282d259;
  font-weight: 500;
  color: #006396;
}



.selectAreaItem {
  border-radius: 0.6rem;
  position: relative;
  box-shadow: 0px 2px 5px 0px #bfbfbf65;
  border: 1px solid #bfbfbfa3;
  transition: all 0.2s ease-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.01);
  }
}

.form__inputsReverse {
  & .q-field__inner {
    border-radius: 2rem;
    border: 2px solid #76b7af;
    padding: 0px 1rem;
  }
  &.q-field--dense.q-field--float .q-field__label{
    display: none;
  }
  &.q-field--labeled.q-field--dense .q-field__native {
    padding-top: 5px;
  }
}

.boxImgReserve {
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

  & img {
    height: 70%;
  }
}

.boxContentV2 {
  background: $primary;
  overflow: hidden;
  height: 8.1rem;
}

.boxItem_v2 {
  border-radius: 0.8rem;
  overflow: visible;
  position: relative;
  // border: 2px solid rgb(3, 156, 195) ;
  width: 100%;
  //box-shadow: 0px 0.1rem 1rem 0px rgba(0, 0, 0, 0.205);
  background-repeat: no-repeat;
  background-size: cover;

  transition: all 0.7s ease-in-out;
  cursor: pointer;
  height: 73%;

  &:hover {
    transform: scale(1.03);
  }
}

@media (max-width: 780px) {

  .boxContentV2 {
    height: 6.5rem;
  }

  .form__inputsReverse {
    & .q-field__inner {

      padding: 0rem 1rem;
    }
  }
}
</style>