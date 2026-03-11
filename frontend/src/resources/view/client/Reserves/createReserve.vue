<script setup>
import { onMounted, ref, inject, watch } from 'vue';
import { Notify } from 'quasar'
import { useRouter, useRoute } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';
import { useReserveStore } from '@/services/store/reserve.store';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
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

const comunAreaStore = useComunAreaStore()
const reserveStore = useReserveStore()
const emitter = inject('emitter')
const comunAreas = ref([])
const selectedComunArea = ref({})
const router = useRouter()
const disabledTime = ref(true)
const ready = ref(false)
const loading = ref(false)
const step = ref(1)
const transitionName = ref('slide-next');
const formData = ref({
  date: '',
  time_from: '',
  time_to: '',
  note: '',
  is_exclusive: false,
})
const hourOptionsFrom = ref([])
const hourOptionsTo = ref([])
const minOptionsFrom = ref([0])
const temporal = ref([])

const selectArea = (id) => {
  selectedComunArea.value = comunAreas.value.find((area) => area.id == id)
  nextStep()
  console.log(selectedComunArea)
}

const backButton = () => {
  if (step.value == 2) {
    cleanForm()
    // return
  }
  step.value--

  emitter.emit('pagTitle', 'Selecciona area común')
}

const nextStep = () => {
  if (step.value == 3) {
    createReserve()
    return
  }
  emitter.emit('pagTitle', step.value == 1 ? 'Agenda tu reserva' : 'Realiza el pago')
  step.value++

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
      setTimeout(() => {
        ready.value = true
      }, 100)
    })
    .catch((response) => {
      showNotify('positive', 'Error al obtener areas comunes')
    })
}
const getAvaibleBookingByDay = () => {

  const data = {
    idArea: selectedComunArea.value.id,
    date: formData.value.date
  }

  reserveStore.getAvailableReserveInDayByArea(data)
    .then((response) => {
      disabledTime.value = false
      hourOptionsFrom.value = response.data.availableFrom
      temporal.value = response.data.availableTo

    })
}
const optionsFn = (date) => {
  return date >= moment().format('YYYY/MM/DD')
}
const limitToTime = () => {
  if (!formData.value.time_from) {
    formData.value.time_to = ''
    hourOptionsTo.value = []
    return
  }

  const fromHour = parseInt(formData.value.time_from.substring(0, 2))
  const maxReserveHours = Number(selectedComunArea.value.max_time_reserve || 0)
  const maxHourCandidates = []

  for (let hour = 1; hour <= maxReserveHours; hour++) {
    maxHourCandidates.push(fromHour + hour)
  }

  // Filtrar según disponibilidad devuelta por la API
  hourOptionsTo.value = temporal.value.filter((hour) => maxHourCandidates.includes(hour))

  timeToDefalutAssing();
}
const timeToDefalutAssing = () => {
  if (hourOptionsTo.value.length > 0) {
    const targetHour = hourOptionsTo.value[hourOptionsTo.value.length - 1];
    const hour = String(targetHour).padStart(2, '0');
    formData.value.time_to = `${hour}:00`;
  } else {
    formData.value.time_to = '';
  }
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
      console.log(response)
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
onMounted(() => {
  getComunsArea()
})

watch(step,
  (toDepth, fromDepth) => {
    console.log(toDepth)
    transitionName.value = toDepth > fromDepth ? 'slide-next' : 'slide-prev';
  }
);
</script>
<template>
  <div class=" h-full " style="overflow: hidden; position: relative;">
    <div class="h-full md:px-20 md:mx-16 " v-if="ready">
      <q-form @submit="nextStep()" class="h-full ">
        <Transition :name="transitionName">
          <div class="px-3 form-step row" v-if="step == 1">
            <div class="col-md-2 col-4 px-1 md:px-0 my-3" v-for="comunArea in comunAreas" :key="comunArea.id">
              <div class="boxItem_v2 px-3" @click="selectArea(comunArea.id)">
                <div class="flex justify-center items-center h-full w-full p-1">
                  <img :src="mediaUrl + '/images/icons/' + comunArea.icon + '.svg'" alt="" style="height:100%">
                </div>
              </div>
              <div class="text-center mt-1  text-title-squad text-ellipsis ellipsis ">
                {{ comunArea.name }}
              </div>
            </div>
          </div>
        </Transition>
        <Transition :name="transitionName">
          <div class="h-full form-step" style="overflow: hidden;" v-if="step > 1">
            <div class=" w-full h-full ">
              <div style="height: 81%; overflow: auto;" class="pb-5">
                <div class="row w-full pt-2">
                  <div class="col-12 px-3">
                    <div class="w-full row selectAreaItem items-center mb-5 pr-4 pl-2 md:px-4 py-2">
                      <div class="text-subtitle1 text-black q-pt-xs col-10 pl-2"
                        style="line-height: 1.2; font-weight: 500;">
                        {{ selectedComunArea.name }}
                      </div>

                      <div class="text-subtitle2 text-white  px-6"
                        style="position: absolute; top: 0; right: 0; border-bottom-left-radius: 1rem; letter-spacing: 1px; letter-spacing: 1px;"
                        :class="{ 'bg-primary': selectedComunArea.pay_label == 'Pago', 'bg-positive': selectedComunArea.pay_label == 'Gratis' }">
                        {{ selectedComunArea.pay_label }}
                      </div>
                      <div class="col-12 row mt-1">
                        <div class="col-3 col-md-1   " style=" border-radius: 0.5rem;">
                          <div class="boxItem_v2" style="height:5rem">
                            <div class="flex justify-start items-center h-full w-full p-1">
                              <img :src="mediaUrl + '/images/icons/' + selectedComunArea.icon + '.svg'" alt=""
                                style="height:100%">
                            </div>
                          </div>
                        </div>
                        <div class="col-8">
                          <div class="q-mt-xs text-body2x text-black" style="font-weight: 500;">
                            Costo: S/{{ selectedComunArea.price }}
                            <i style="font-weight: 500;" v-if="selectedComunArea.warranty_price > 0">
                              + S/{{ selectedComunArea.warranty_price }}
                              <span class="text-warning" style="font-size: 0.75rem;">garantia</span>
                            </i>
                          </div>
                          <div class="q-mt-xs text-body2x text-black" style="font-weight: 500;">
                            Capcidad: {{ selectedComunArea.capacity }} personas
                          </div>
                          <div class="q-mt-xs text-body2x text-black" style="font-weight: 500;">
                            Max. de reserva: {{ selectedComunArea.max_time_reserve }} hora(s)
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <template v-if="step == 2">
                    <div class="col-12 col-md-6 row">
                      <div class="col-12 row md:px-5 ">
                        <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                          Selecciona la fecha
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black" style="font-weight: medium;">
                              Fecha de reserva:
                            </div>
                            <q-input v-model="formData.date" :rules="[val => !(!val) || 'Fecha es requerida']" dense
                              borderless clearable class="form__inputsReverse mt-1" color="primary">
                              <template v-slot:append>
                                <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-date mask="DD-MM-YYYY" v-model="formData.date" :options="optionsFn"
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
                      </div>
                      <div class="col-12 row md:px-5">
                        <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                          Selecciona la hora
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2">
                          <div class="col-6  pr-2 md:pr-4">
                            <div class="text-subtitle2 text-black" style="font-weight: medium;">
                              Desde:
                            </div>
                            <q-input v-model="formData.time_from" mask="time" :rules="['time']" dense borderless
                              clearable class="form__inputsReverse mt-1 q-pb-sm" color="primary"
                              :readonly="disabledTime" :disable="disabledTime">
                              <template v-slot:append>
                                <q-icon name="eva-clock-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-time v-model="formData.time_from" :hour-options="hourOptionsFrom"
                                      :minute-options="minOptionsFrom" @update:model-value="limitToTime" format24h>
                                      <!-- <q-time v-model="formData.time_from" mask="hh:ss A" :options="hourAvailable"> -->

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
                            <div class="text-subtitle2 text-black" style="font-weight: medium;">
                              Hasta:
                            </div>
                            <q-input v-model="formData.time_to" mask="time" :rules="['time']" dense borderless clearable
                              class="form__inputsReverse mt-1 q-pb-sm" color="primary" :readonly="disabledTime"
                              :disable="disabledTime">
                              <template v-slot:append>
                                <q-icon name="eva-clock-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-time v-model="formData.time_to" format24h :hour-options="hourOptionsTo"
                                      :minute-options="minOptionsFrom">
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
                        <div class="col-12 row mt-0 px-3 md:px-2" v-if="selectedComunArea.type == 2">
                          <q-checkbox v-model="formData.is_exclusive" color="primary">
                            <div class="text-grey-9 mt-1">
                              Reservar de forma exclusiva
                            </div>
                          </q-checkbox>
                        </div>
                      </div>
                    </div>
                    <div class="col-12 col-md-6  md:px-5">
                      <div class="w-full text-subtitle1 headerSection my-1 py-2 px-4">
                        Reglas
                      </div>
                      <div class="col-12 row mt-3 px-3 md:px-2">
                        <div class="text-grey-9" style="font-size: 0.95rem; line-height: 1.7;"
                          v-html="selectedComunArea.format_rules" />
                      </div>
                    </div>

                  </template>
                  <template v-if="step == 3">
                    <div class="col-12 col-md-6 row md:px-5">
                      <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                        Información adicional de reserva
                      </div>
                      <div class="col-12 row mt-3 px-3 md:px-2">
                        <div class="col-12">
                          <div class="text-subtitle2 text-black" style="font-weight: medium;">
                            Notas
                          </div>
                          <q-input dense borderless clearable type="textarea" v-model="formData.note"
                            class="form__inputsReverse mt-1" color="primary" />
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
              <div style="height: 19%;" class="buttonSection">
                <div class="row py-4 ">
                  <div class="col-12 pb-4 px-5">
                    <div class="w-full flex justify-between text-black">
                      <div style="font-weight: 500; font-size: 1.1rem;">Total a pagar:</div>
                      <div style="font-weight: 500; font-size: 1.1rem;">S/{{ selectedComunArea.price +
                        selectedComunArea.warranty_price }}</div>
                    </div>
                  </div>
                  <div class=" col-12 row  ">
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
                  </div>
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
.form-step {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;

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

  &:hover {
    transform: scale(1.03);
  }
}

@media (max-width: 780px) {

  .buttonSection {
    box-shadow: 0px -5px 10px 0px rgb(207 207 207);
    // padding-bottom: max(var(--safe-area-inset-bottom, env(safe-area-inset-bottom, 0px)), 48px);
  }

  .form__inputsReverse {
    & .q-field__inner {

      padding: 0.1rem 1rem;
    }
  }
}
</style>