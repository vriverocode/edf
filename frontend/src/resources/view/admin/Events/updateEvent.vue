<script setup>
import { onMounted, ref, inject } from 'vue';
import { Notify } from 'quasar'
import { useRouter, useRoute } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';
import { useEventStore } from '@/services/store/event.store';
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
const eventStore = useEventStore()
const comunAreas = ref([])
const router = useRouter()
const route = useRoute()
const ready = ref(false)
const loading = ref(false)
const step = ref(1)
const locationOption = [
  { value:-1, name:'Seleccione una opción'},
  { value: 1, name:'Si'},
  { value: 2, name:'No'},
]

const event = ref({})
const minOptionsFrom = ref([0])


const backButton = () => {
  if (step.value == 2) {    // return
  }
  step.value--
}

const nextStep = () => {
  if (step.value == 2) {
    createEvent()
    return
  }
  step.value++

}

const getComunsArea = () => {
  comunAreaStore.getAllComunAreas()
    .then((response) => {
      if (response.code !== 200) throw response
      comunAreas.value = response.data
    })
    .catch((response) => {
      showNotify('positive', 'Error al obtener areas comunes')
    })
}

const optionsFn = (date) => {
  return date >= moment().format('YYYY/MM/DD')
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const createEvent = () => {
  loading.value = true
  cleanAnother(event.value.typeArea)
  const dataEventForm =  new FormData
  dataEventForm.append('title', event.value.title)
  dataEventForm.append('description', event.value.description)
  dataEventForm.append('date', event.value.date)
  dataEventForm.append('time_from', event.value.time_from)
  dataEventForm.append('time_to', event.value.time_to)
  dataEventForm.append('type_location', event.value.typeArea)
  if(event.value.typeArea == 1) {
    dataEventForm.append('area', event.value.area.id)
  }
  dataEventForm.append('location', event.value.location)


  eventStore.updateEvent(dataEventForm, event.value.id)
    .then((response) => {
      console.error(response)
      showNotify('positive', 'Evento editado con exito')
      setTimeout(() => {
        loading.value = false
        router.go(-1)
      }, 500);

    })
    .catch((response) => {
      console.error(response)
      setTimeout(() => {
        loading.value = false
        showNotify('negative', 'Error al editar evento')
      }, 1000);

    })
}
const cleanAnother = (e) => {
  if(e == 1){
    event.value.location = '.'
  }
  if(e == 2){
    event.value.area = ''
  }
}

const getEventToUpdate = () => {
  eventStore.getEventById(route.params.id)
  .then((data) => {
    event.value = data.data
    event.value.typeArea = event.value.booking_id ? 1 : 2
    event.value.area = event.value.booking?.comun_area
    setTimeout(() => {
      ready.value = true
    }, 1000)
  })
  .catch((response) => {
    console.error(response)
  })
}
onMounted(() => {
  getComunsArea()
  getEventToUpdate()
})

</script>
<template>
  <div class="md:px-20 h-full " style="overflow: hidden; position: relative;">
    <div class="h-full" v-if="ready">
      <q-form @submit="nextStep()" class="h-full ">
        <div style="height: 90%; overflow-x: hidden;" >
          <Transition name="horizontal">
            <div class="h-full"  v-if="step == 1 ">
              <div class=" w-full h-full ">
                <div class="pb-10">
                  <div class="row w-full pt-5">
                    
                    <div class="col-12 row">
                      <div class="col-12 row md:px-5 ">
                        <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                          Datos del evento
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black">
                              Titulo del evento
                            </div>
                            <q-input
                              dense
                              borderless
                              clearable
                              v-model="event.title"
                              class="form__inputsReverse mt-1"
                              color="primary"
                              :rules="[ val => val && val.length > 0 || 'Nombre de area es requerido']"
                            />
                          </div>
                          <div class="col-12 mb-2">
                            <div class="text-subtitle2 text-black" style="font-weight: medium;">
                              Descripción
                            </div>
                            <q-input dense borderless clearable type="textarea" v-model="event.description"
                              class="form__inputsReverse mt-1" color="primary" />
                          </div>
                        </div>
                      </div>
                      <div class="col-12 row md:px-5 ">
                        <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                          Selecciona la fecha
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black" style="font-weight: medium;">
                              Fecha del evento:
                            </div>
                            <q-input v-model="event.date" :rules="[val => !(!val) || 'Fecha es requerida']" dense
                              borderless clearable class="form__inputsReverse mt-1" color="primary">
                              <template v-slot:append>
                                <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-date mask="DD-MM-YYYY" v-model="event.date" :options="optionsFn"
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
                            <q-input v-model="event.time_from" mask="time"
                              dense borderless
                              clearable class="form__inputsReverse mt-1 q-pb-sm" color="primary">
                              <template v-slot:append>
                                <q-icon name="eva-clock-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-time v-model="event.time_from"
                                      :minute-options="minOptionsFrom" format24h>
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
                            <q-input v-model="event.time_to" mask="time" dense borderless clearable
                              class="form__inputsReverse mt-1 q-pb-sm" color="primary" >
                              <template v-slot:append>
                                <q-icon name="eva-clock-outline" class="cursor-pointer">
                                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                    <q-time v-model="event.time_to" format24h 
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
                      </div>
                    </div>
  
                  </div>
                </div>
              </div>
            </div>
          </Transition>
          <Transition name="horizontal">
            <div class="h-full"  v-if="step == 2">
              <div class=" w-full h-full ">
                <div class="pb-10">
                  <div class="row w-full pt-5">
                    <div class="col-12 row">
                      <div class="col-12 row md:px-5 ">
                        <div class="col-12 text-subtitle1 headerSection my-1 py-2 px-4">
                          Datos de localidad
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black">
                              Usar area común
                            </div>
                            <q-select 
                              class="form__inputsReverse mt-1"
                              v-model="event.typeArea"
                              :options="locationOption"
                              option-label="name"
                              option-value="value"
                              emit-value
                              map-options
                              :rules="[ val => val != -1 || 'Debes seleccionar un recinto']"
                              dense borderless />
                              
                          </div>
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2" v-if="event.typeArea == 1">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black">
                              Selecciona el recinto/ubicación
                            </div>
                            <q-select 
                              class="form__inputsReverse mt-1"
                              v-model="event.area"
                              :options="comunAreas"
                              option-label="name"
                              option-value="value"
                              emit-value
                              map-options
                              :rules="[ val => val.value != -1 || 'Debes seleccionar un recinto']"
                              dense borderless />
                              <div class="text-grey-7" style="font-size: 0.7rem;">
                                Al escoger un area común como recinto para el evento, se realizara una reservación exclusiva del area seleccionada, con la fecha y horas asignados en este formulario
                              </div>
                          </div>
                        </div>
                        <div class="col-12 row mt-3 px-3 md:px-2" v-if="event.typeArea == 2">
                          <div class="col-12">
                            <div class="text-subtitle2 text-black">
                              Ubicación/Lugar
                            </div>
                            <q-input
                              dense
                              borderless
                              clearable
                              v-model="event.location"
                              class="form__inputsReverse mt-1"
                              color="primary"
                              :rules="[ val => val && val.length > 0 || 'Nombre de area es requerido']" />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>
        <div style="height: 10%;" class="buttonSection">
          <div class="row py-4 ">
            <div class="col-6 flex flex-center ">
              <q-btn color="grey-8" class="" style="width: 90%; border-radius: 0.5rem;" @click="backButton()"
                v-if="step > 1">
                <div class="py-1 md:py-1">
                  Volver
                </div>
              </q-btn>
            </div>
            <div class=" flex flex-center" :class=" step == 1 ? 'col-12' : 'col-6'">
              <q-btn color="primary" class="" style="width: 90%; border-radius: 0.5rem;" type="submit"
                :loading="loading">
                <div class="py-1 md:py-1">
                  {{ step == 3 ? 'Guardar reserva' : 'Siguiente' }}
                </div>
              </q-btn>
            </div>
          </div>
        </div>
      </q-form>
    </div>
    <div v-else class="flex flex-center py-24 w-full">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss">
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
w
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
@media (max-width: 780px) {
  .buttonSection {
    box-shadow: 0px -5px 10px 0px rgb(207 207 207)
  }
  .form__inputsReverse {
    & .q-field__inner {

      padding: 0.1rem 1rem;
    }
  }
}
</style>