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

const comunAreaStore = useComunAreaStore()
const reserveStore = useReserveStore()
const emitter = inject('emitter')
const comunAreas = ref([])
const selectedComunArea = ref({})
const router = useRouter()
const disabledTime = ref(true)
const ready = ref(false)
const loading = ref(false)
const step = ref(3)
const transitionName = ref('slide-next');
const formData = ref({
  date: '2026/03/25',
  time_from: '',
  time_to: '',
  note: '',
  is_exclusive: false,
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
const selectedInterval = ref({})
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
  if (step.value == 3) {
    createReserve()
    return
  }
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
      setTimeout(() => {
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
        showNotify('positive', 'Reserva realizada con exito')

        if (!response.data.toPay) {
          router.push('/client/reserves/confirm-reserve/' + response.data.id)
          return
        }

        router.push('/client/reserves/pay-reserve/' + response.data.id)

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
  element.style.display = visible ? 'flex' : 'none'
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
  formData.value.from = selectedInterval.value.from
  selectedInterval.value.id = index;
}

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
                <div class="relative flex justify-between items-center">
                  <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-full h-1.5 bg-[#d9dee8] z-0"></div>
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

                <div class="text-center text-[#5571b7] font-bold text-lg md:text-xl mt-3 mb-4">
                  <div class="font-bold text-2xl" v-if="step === 1">Selecciona área común</div>
                  <div class="font-bold text-2xl" v-else-if="step === 2">Fecha de reserva</div>
                  <div class="font-bold text-2xl" v-else-if="step === 3">Selecciona la hora</div>
                  <div class="font-bold text-2xl" v-else>Confirmación de pago</div>
                </div>
              </div>
              <div style="height: 70%; overflow: auto;" class="pb-5">
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
                            :class="{ 'active': tapActive == 'ma' }" @click="tapActive = 'ma'">Mañana</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'ta' }" @click="tapActive = 'ta'">Tarde</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'no' }" @click="tapActive = 'no'">Noche</div>
                        </div>
                        <div class="mt-2 blockHoursContent  py-2 px-3">
                          <template v-if="intervalHorarys[tapActive].length > 0">
                            <div class="flex items-center justify-between blockHoursContent__item my-2 px-4 py-1"
                              v-for="(interval, index) in intervalHorarys[tapActive]"
                              :class="{ 'activeHour': selectedInterval?.id == index }" :key="index"
                              @click="setSelectHour(index)">
                              <div class="text_interval">{{ interval.time_from }} - {{
                                interval.time_to }}</div>
                              <div>
                                <q-chip :color="setColor(interval.status)" text-color="white" size="0.8rem">
                                  <div style="font-size: 0.6rem;" class="py-8">
                                    {{ interval.status }}
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
                      <div>
                          
                      </div>
                    </div>
                  </template>
                </div>
              </div>
              <div style="height: 15%;" class="buttonSection">
                <div class="row py-4 ">
                  <template v-if="step == 4">
                    <div class="col-6 flex flex-center ">
                      <q-btn color="grey-8" class="" style="width: 90%; border-radius: 0.5rem;" @click="backButton()"
                        v-if="step > 1">
                        <div class="py-1 md:py-1">
                          Volver
                        </div>
                      </q-btn>
                    </div>
                    <div class="col-6 flex flex-center">
                      <q-btn color="primary" class="" style="width: 90%; border-radius: 0.5rem;" type="submit"
                        :loading="loading">
                        <div class="py-1 md:py-1">
                          {{ step == 3 ? 'Guardar reserva' : 'Siguiente' }}
                        </div>
                      </q-btn>
                    </div>
                  </template>
                  <template v-if="step == 4">
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
                </div>
              </div>


            </div>
          </div>
        </Transition>
      </q-form>
    </div>
    <div v-else class="flex flex-center py-24 w-full">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss">
.text-title-squadV2 {
  color: #1e5f96;
  font-weight: 500;
  font-size: 0.9rem;
}

.text_interval {
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.2s ease-in-out;
}

.blockHoursContent {
  border: 2px solid lightgrey;
  border-radius: 0.7rem;

  &__item {
    border: 2px solid lightgray;
    border-radius: 0.8rem;
    transition: all 0.2s ease-in-out;

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
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
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

      padding: 0.1rem 1rem;
    }
  }
}
</style>