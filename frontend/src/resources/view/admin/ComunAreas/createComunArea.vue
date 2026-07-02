<script setup>
import { onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar'
import { useRouter } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';

const comunAreaStore = useComunAreaStore()
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL
const router = useRouter()
const loading = ref(false)
const step = ref(0)
const iconsOption = [
  { value: 'default', name: 'Por defecto' },
  { value: 'arcade', name: 'Arcade' },
  { value: 'cine', name: 'Cine' },
  { value: 'coworking', name: 'Coworking' },
  { value: 'gimnasio', name: 'Gimnasio' },
  { value: 'juego_de_salon', name: 'Juego de salón' },
  { value: 'lounge', name: 'Lounge' },
  { value: 'parrilla', name: 'Parilla' },
  { value: 'piscina', name: 'Piscina' },
  { value: 'sauna', name: 'Sauna' },
]
const typeArea = [
  { value: 1, name: 'Uso gratuito' },
  { value: 2, name: 'Uso mixto (Compartido y exclusivo)' },
  { value: 3, name: 'De pago' },
  { value: 4, name: 'De pago lista de invitados' },
]
const severityOptions = [
  { value: 1, name: 'Leve' },
  { value: 2, name: 'Grave' },
  { value: 3, name: 'Muy grave' }
]

const ruleTypeOptions = [
  { value: 1, name: 'Norma de convivencia (Sin multa)' },
  { value: 2, name: 'Falta con amonestación monetaria' }
]

const defaultDays = [
  { day: 1, label: 'Lunes' }, { day: 2, label: 'Martes' }, { day: 3, label: 'Miércoles' },
  { day: 4, label: 'Jueves' }, { day: 5, label: 'Viernes' }, { day: 6, label: 'Sábado' }, { day: 0, label: 'Domingo' }
];
const dayNotAvailable = [
  'Lunes',
  'Martes',
  'Miercoles',
  'Jueves',
  'Viernes',
  'Sabado',
  'Domingo',
]
const formData = ref({
  name: '',
  capacity: '',
  price: 0,
  warrantyPrice: 0,
  description: '',
  maxTime: 1,
  maxTimeExclusive: 1,
  max_cupo: 0,
  has_extension: false,
  max_time_extension: null,
  extension_price: null,
  schedules: defaultDays.map(d => ({
    day: d.day,
    label: d.label,
    isOpen: true, // Por defecto marcamos abiertos
    intervals: [{ from: '08:00', to: '18:00' }] // Un turno por defecto
  })),
  icon: { value: 'default', name: 'Por defecto' },
  typeArea: { value: 1, name: 'Uso gratuito' },
  rulesList: [
    { title: '', code: '', description: '', type: ruleTypeOptions[0], severity: severityOptions[0], suggest_amount: null }
  ]
})

const addRule = () => {
  formData.value.rulesList.push({
    title: '',
    code: '',
    description: '',
    type: ruleTypeOptions[0],
    severity: severityOptions[0],
    suggest_amount: null
  })
}

const removeRule = (index) => {
  formData.value.rulesList.splice(index, 1)
}
const backButton = () => {
  step.value--
}
const nextStep = () => {
  if (step.value == 3) {
    createArea()
    return
  }

  step.value++
}
const createArea = () => {
  loading.value = true
  formData.value.imageIcon = formData.value.icon.value;
  formData.value.type = formData.value.typeArea.value;

  comunAreaStore.createComunArea(formData.value)
    .then((response) => {
      if (response.code !== 200) throw response


      showNotify('positive', 'Area común creada con exito')
      setTimeout(() => {
        loading.value = false
        router.go(-1)

      }, 1000)
    })
    .catch((response) => {
      loading.value = false

    })
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const toggleDay = (schedule) => {
  if (schedule.isOpen && schedule.intervals.length === 0) {
    schedule.intervals.push({ from: '08:00', to: '18:00' });
  } else if (!schedule.isOpen) {
    schedule.intervals = [];
  }
};

const addInterval = (schedule) => {
  schedule.intervals.push({ from: '', to: '' });
};

const removeInterval = (schedule, index) => {
  schedule.intervals.splice(index, 1);
  if (schedule.intervals.length === 0) {
    schedule.isOpen = false;
  }
};

onMounted(() => {
})

</script>
<template>
  <div class="md:px-24 px-2 h-full" style="overflow: hidden; ">
    <div class="text-center text-black text-h5 text-bold  md:mb-8 mb-2 headerForm">
      Datos del area común
    </div>
    <q-form @submit="nextStep()" class="formContent" style="overflow: auto;">

      <div class=" w-full h-full">
        <Transition name="horizontal">

          <div class="row w-full" style="height:85%; overflow:auto" v-if="step == 0">
            <div class="col-md-6 col-12 mt-1 mb-4 md:mt-0 px-2 md:px-12">
              <div class="boxImgStore">
                <img :src="urlMedia + '/images/icons/' + formData.icon.value + '.svg'" alt="">
              </div>
            </div>
            <div class="col-md-6 col-12 mt-1 mb-4 px-2 md:mt-0 md:px-12">
              <div class="text-subtitle2 text-black">
                Icono
              </div>
              <q-select class="form__inputsR mt-1" v-model="formData.icon" :options="iconsOption" option-label="name"
                option-value="value" dense borderless />
            </div>
            <div class="col-md-6 col-12 mt-1 mb-4 px-2 md:mt-0 md:px-12">
              <div class="text-subtitle2 text-black">
                Tipo de area común
              </div>
              <q-select class="form__inputsR mt-1" v-model="formData.typeArea" :options="typeArea" option-label="name"
                option-value="value" dense borderless />
            </div>
            <div class="col-md-6 col-12 mt-1 md:mt-0 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Nombre del area
              </div>
              <q-input dense borderless clearable v-model="formData.name" class="form__inputsR mt-1" color="primary"
                :rules="[val => val && val.length > 0 || 'Nombre de area es requerido']" />
            </div>
            <div class="col-md-6 col-12 mt-1 md:mt-0 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Aforo
              </div>
              <q-input dense borderless clearable v-model="formData.capacity" class="form__inputsR mt-1" color="primary"
                :rules="[val => !(!val) || 'El aforo es requerido']" />
            </div>


            <div class="col-md-6 col-12 mt-2 md:mt-0 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Descripción
              </div>
              <q-input 
              
              borderless clearable v-model="formData.description" 
              type="textarea"
              autogrow
              class="form__inputsR mt-1" color="primary" />
            </div>
          </div>
        </Transition>
        <Transition name="horizontal">
          <div class="row w-full" style="height:85%; overflow:auto" v-if="step == 1">
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12" v-if="formData.typeArea.value != 1">
              <div class="text-subtitle2 text-black">
                Precio por reserva
              </div>
              <q-input dense borderless clearable v-model="formData.price" class="form__inputsR mt-1" color="primary"
                hint="Dejarlo en 0 si no requiere reserva" />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12" v-if="formData.typeArea.value != 1">
              <div class="text-subtitle2 text-black">
                Precio de garantia
              </div>
              <q-input dense borderless clearable v-model="formData.warrantyPrice" class="form__inputsR mt-1"
                color="primary" hint="Dejar en 0 si no aplica garantia" />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Maximo de cupos de reservas
              </div>
              <q-input dense borderless clearable v-model="formData.max_cupo" class="form__inputsR mt-1" color="primary"
                :rules="[val => !(!val) || 'Establece un maximo de cupos']" />
            </div>
            
            <div class="col-md-6 col-12  row mt-1 px-2 md:px-12" >
              <div class="col-12">
                <div class="text-subtitle2 text-black ">
                  Maximo de horas de reserva
                </div>
                <q-input dense borderless clearable v-model="formData.maxTime" class="form__inputsR mt-1" autofocus
                  color="primary" :rules="[val => !(!val) || 'Las horas maxima de reserva es necesaria']" />
              </div>
            </div>
            <div class="col-md-6 col-12  row mt-1 px-2 md:px-12" v-if="formData.typeArea.value == 2">
              <div class="col-12">
                <div class="text-subtitle2 text-black ">
                  Maximo de horas de reserva exclusiva
                </div>
                <q-input dense borderless clearable v-model="formData.maxTimeExclusive" class="form__inputsR mt-1" autofocus
                  color="primary" :rules="[val => !(!val) || 'Las horas maxima de reserva es necesaria']" />
              </div>
            </div>

            <!-- Extensiones -->
            
            <template v-if="formData.has_extension">
              <div class="col-md-6 col-12 mt-0 px-2 md:px-12">
                <div class="text-subtitle2 text-black">Máximo de horas de extensión</div>
                <q-input dense borderless clearable type="number" v-model="formData.max_time_extension"
                  class="form__inputsR mt-1" color="primary" hint="Horas adicionales permitidas"
                  :rules="[val => !!val || 'Requerido cuando las extensiones están habilitadas']" />
              </div>
              <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
                <div class="text-subtitle2 text-black">Precio de la extensión (S/)</div>
                <q-input dense borderless clearable type="number" v-model="formData.extension_price"
                  class="form__inputsR mt-1" color="primary" hint="Costo de la extensión de tiempo"
                  :rules="[val => !!val || 'Requerido cuando las extensiones están habilitadas']" />
              </div>
            </template>
            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black mb-1">Extensiones de reserva</div>
              <q-toggle v-model="formData.has_extension" label="Habilitar extensiones" color="primary" />
            </div>
          </div>
        </Transition>
        <Transition name="horizontal">
          <div class="row w-full" style="height:85%; overflow:auto" v-if="step == 2">
            <div class="col-12 mt-4 px-2 md:px-12">
              <div class="text-subtitle1 text-bold text-black mb-2">
                Horarios de disponibilidad por día
              </div>
              <div class="q-pr-sm">
                <div v-for="(schedule, index) in formData.schedules" :key="index" class="q-mb-md rounded-borders"
                  style="border: 1px solid #bfbfbfa3; padding: 10px;">
                  <div class="row items-center justify-between">
                    <q-toggle v-model="schedule.isOpen" :label="schedule.label" color="primary" keep-color
                      @update:model-value="toggleDay(schedule)" />
                    <q-btn v-if="schedule.isOpen" icon="eva-plus-outline" label="Añadir Turno" size="sm" color="primary"
                      flat rounded @click="addInterval(schedule)" />
                  </div>
                  <div v-if="schedule.isOpen" class="q-mt-sm">
                    <div v-for="(interval, idx) in schedule.intervals" :key="idx"
                      class="row q-col-gutter-sm items-center q-mb-sm pl-2">
                      <div class="col-5">
                        <div class="text-caption text-grey-8">Desde:</div>
                        <q-input v-model="interval.from" mask="time" :rules="['time']" dense borderless
                          class="form__inputsR" color="primary">
                          <template v-slot:append>
                            <q-icon name="eva-clock-outline" class="cursor-pointer">
                              <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                <q-time v-model="interval.from">
                                  <div class="row items-center justify-end">
                                    <q-btn v-close-popup label="Aceptar" color="primary" flat />
                                  </div>
                                </q-time>
                              </q-popup-proxy>
                            </q-icon>
                          </template>
                        </q-input>
                      </div>
                      <div class="col-5">
                        <div class="text-caption text-grey-8">Hasta:</div>
                        <q-input v-model="interval.to" mask="time" :rules="['time']" dense borderless
                          class="form__inputsR" color="primary">
                          <template v-slot:append>
                            <q-icon name="eva-clock-outline" class="cursor-pointer">
                              <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                <q-time v-model="interval.to">
                                  <div class="row items-center justify-end">
                                    <q-btn v-close-popup label="Aceptar" color="primary" flat />
                                  </div>
                                </q-time>
                              </q-popup-proxy>
                            </q-icon>
                          </template>
                        </q-input>
                      </div>
                      <div class="col-2 flex flex-center mt-4">
                        <q-btn icon="eva-trash-2-outline" color="negative" flat dense round
                          @click="removeInterval(schedule, idx)" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
        <Transition name="horizontal">
          <div class="w-full" style="height:85%; overflow:hidden" v-if="step == 3">
            <div class="col-12 px-2 md:px-12 w-full h-full">
              <div class="row justify-between items-center bg-white " style="height: 15%;">
                <div class="text-subtitle1 text-bold text-black">
                  Reglas y Normas del Área
                </div>
                <div>
                  <q-btn color="primary" label="Agregar regla" @click="addRule" outline rounded dense class="px-3" />
                </div>
              </div>
              <div style="height: 85%; overflow: auto;">
                <div v-for="(rule, index) in formData.rulesList" :key="index"
                  class="row w-full relative-position md:mt-14 pb-8 mt-5" style="">
                  <q-btn v-if="formData.rulesList.length > 1" icon="eva-trash-2-outline" color="negative" dense outline
                    round @click="removeRule(index)" class="absolute-top-right"
                    style="top: -15px; right: 5px; z-index: 10;" />

                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">
                      Título de la regla
                    </div>
                    <q-input dense borderless clearable v-model="rule.title" class="form__inputsR mt-1 bg-white"
                      color="primary" :rules="[val => !!val || 'El título es requerido']" />
                  </div>
                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">
                      N° del articulo
                    </div>
                    <q-input dense borderless clearable v-model="rule.code" class="form__inputsR mt-1 bg-white"
                      color="primary" :rules="[val => !!val || 'N° del articulo del reglamento es requerido']" />
                  </div>

                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">
                      Nivel de severidad
                    </div>
                    <q-select class="form__inputsR mt-1 bg-white" dense v-model="rule.severity"
                      :options="severityOptions" option-label="name" option-value="value" borderless />
                  </div>

                  <div class="col-md-6 col-12 mt-4 px-2">
                    <div class="text-subtitle2 text-black">
                      Tipo de regla
                    </div>
                    <q-select class="form__inputsR mt-1 bg-white" dense v-model="rule.type" :options="ruleTypeOptions"
                      option-label="name" option-value="value" borderless />
                  </div>

                  <div class="col-md-6 col-12 mt-4 px-2" v-if="rule.type.value === 2">
                    <div class="text-subtitle2 text-black">
                      Amonestación Monetaria sugerida
                    </div>
                    <q-input dense borderless clearable type="number" v-model="rule.suggest_amount"
                      class="form__inputsR mt-1 bg-white" color="primary" hint="Monto a multar"
                      :rules="[val => !!val || 'El monto es requerido para este tipo de regla']" />
                  </div>

                  <div class="col-12 col-md-6 mt-4 px-2">
                    <div class="text-subtitle2 text-black">
                      Descripción de la regla
                    </div>
                    <q-input borderless clearable type="textarea" rows="2" v-model="rule.description"
                      class="form__inputsR mt-1 bg-white" dense color="primary" />
                  </div>

                </div>
              </div>

            </div>
          </div>
        </Transition>
        <div class="w-full flex flex-center pt-1" style="height:10%; overflow:hidden">
          <div class="w-full px-2 md:px-12 flex justify-center">
            <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="backButton()" v-if="step >= 1" class="me-7">
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

  & img {
    height: 70%;
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