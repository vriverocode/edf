<script setup>
import { onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';

const comunAreaStore = useComunAreaStore()
const router = useRouter()
const route = useRoute()
const comunArea = ref({})
const loading = ref(false)
const step = ref(0)
const ready = ref(false)
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL

const backButton = () => {
  step.value--
}
const nextStep = () => {
  // Cuando se llega al paso 2, el botón ejecuta el update en lugar de seguir sumando
  if (step.value === 2) {
    updateComunArea();
    return;
  }
  step.value++;
};
const updateComunArea = () => {
  loading.value = true
  comunArea.value.warrantyPrice = comunArea.value.warranty_price
  comunArea.value.maxTime = comunArea.value.max_time_reserve

  comunAreaStore.updateComunArea(comunArea.value)
    .then((response) => {
      if (response.code !== 200) throw response
      showNotify('positive', 'Area común actualizada con exito')
      setTimeout(() => {
        loading.value = false
        router.go(-1)

      }, 1000)
    })
    .catch((response) => {
      loading.value = false
      showNotify('negative', response)
    })
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const getComunAreaById = () => {
  comunAreaStore.getComunAreaById(route.params.id)
    .then((response) => {
      formatedData(response);
    })
    .catch((response) => {
      console.log(response)
    })
}

// =========== CONSTANTES Y OPCIONES ===========
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
];

const typeArea = [
  { value: 1, name: 'Uso gratuito' },
  { value: 2, name: 'Uso mixto (Compartido y exclusivo)' },
  { value: 3, name: 'De pago' },
  { value: 4, name: 'De pago lista de invitados' },
];

const dayNotAvailable = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];

const severityOptions = [
  { value: 1, name: 'Leve' },
  { value: 2, name: 'Grave' },
  { value: 3, name: 'Muy grave' }
];

const ruleTypeOptions = [
  { value: 1, name: 'Norma de convivencia (Sin multa)' },
  { value: 2, name: 'Falta con amonestación monetaria' }
];

// Funciones para agregar/quitar reglas en la vista
const addRule = () => {
  comunArea.value.rulesList.push({
    id: null, // Importante: id nulo significa que es nueva
    title: '',
    code: '',
    description: '',
    type: ruleTypeOptions[0],
    severity: severityOptions[0],
    suggest_amount: null
  })
}

const removeRule = (index) => {
  comunArea.value.rulesList.splice(index, 1)
}

const formatedData = (response) => {
  try {
    // Aquí asumo que traes la data del store pasandole el ID de la ruta

    let data = response.data; // Adapta esto a la respuesta de tu backend

    // 1. Mapear Selects principales
    data.type = typeArea.find(t => t.value === data.type) || typeArea[0];
    data.icon = iconsOption.find(i => i.value === data.icon) || iconsOption[0];

    // 2. Mapear días no disponibles (decodificar el JSON de la BD)
    try {
      data.notAvailable = typeof data.not_available_days === 'string'
        ? JSON.parse(data.not_available_days)
        : (data.not_available_days || []);
    } catch (e) { data.notAvailable = [] }

    // 3. Mapear las reglas (Convertir los enteros devuelta a Objetos para los selects)
    if (data.rules_area && data.rules_area.length > 0) {
      data.rulesList = data.rules_area.map(rule => {
        return {
          id: rule.id, // FUNDAMENTAL MANTENER EL ID
          title: rule.title,
          code: rule.code,
          description: rule.description,
          suggest_amount: rule.suggest_amount,
          type: ruleTypeOptions.find(t => t.value === rule.type) || ruleTypeOptions[0],
          severity: severityOptions.find(s => s.value === rule.severity) || severityOptions[0]
        }
      })
    } else {
      // Si el area no tenía reglas registradas, dejamos una en blanco
      data.rulesList = [{ id: null, title: '', description: '', type: ruleTypeOptions[0], severity: severityOptions[0], suggest_amount: null }];
    }

    comunArea.value = data;
    ready.value = true;

  } catch (error) {
    console.log(error)
    showNotify('negative', 'Error al cargar el área');
  }
}
// CUANDO OBTENGAS LA DATA DEL BACKEND (Por ejemplo en tu onMounted):
onMounted(async () => {
  getComunAreaById()
})

</script>
<template>
  <div class="md:px-24 px-2 h-full" style="overflow: hidden; ">
    <div class="text-center text-black text-h5 text-bold  md:mb-8 mb-2 headerForm">
      Editar Área Común
    </div>

    <q-form @submit="nextStep()" class="formContent">
      <div class="h-full" v-if="ready">

        <Transition name="horizontal">
          <div class="row w-full" style="height:85%; overflow:auto" v-if="step == 0">
            <div class="col-md-6 col-12 mt-1 mb-4 md:mt-0 px-2 md:px-12">
              <div class="boxImgStore">
                <img :src="urlMedia + '/images/icons/' + comunArea.icon.value + '.svg'" alt="">
              </div>
            </div>
            <div class="col-md-6 col-12 mt-0 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Icono</div>
              <q-select dense borderless v-model="comunArea.icon" :options="iconsOption" option-label="name"
                option-value="value" class="form__inputsR mt-1 bg-white" />
            </div>
            <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Nombre del Área</div>
              <q-input dense borderless clearable v-model="comunArea.name" class="form__inputsR mt-1" color="primary"
                :rules="[val => !!val || 'El nombre es requerido']" />
            </div>

            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Tipo de área</div>
              <q-select dense borderless v-model="comunArea.type" :options="typeArea" option-label="name"
                option-value="value" class="form__inputsR mt-1 bg-white" />
            </div>

            <div class="col-md-6 col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Aforo</div>
              <q-input dense borderless clearable type="number" v-model="comunArea.capacity" class="form__inputsR mt-1"
                color="primary" :rules="[val => !!val || 'La capacidad es requerida']" />
            </div>



            <div class="col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Descripción</div>
              <q-input dense borderless clearable type="textarea" rows="3" v-model="comunArea.description"
                class="form__inputsR mt-1" color="primary" />
            </div>

          </div>
        </Transition>

        <Transition name="horizontal">
          <div class="row w-full" style="height:85%; overflow:auto" v-if="step == 1">

            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Precio (0 si es gratis)</div>
              <q-input dense borderless clearable type="number" v-model="comunArea.price" class="form__inputsR mt-1"
                color="primary" />
            </div>

            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Precio de garantía</div>
              <q-input dense borderless clearable type="number" v-model="comunArea.warranty_price"
                class="form__inputsR mt-1" color="primary" />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Cupos máximos</div>
              <q-input dense borderless clearable type="number" v-model="comunArea.max_cupo" class="form__inputsR mt-1"
                color="primary" />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Tiempo máximo de reserva (Horas)</div>
              <q-input dense borderless clearable type="number" v-model="comunArea.max_time_reserve"
                class="form__inputsR mt-1" color="primary" :rules="[val => !!val || 'Requerido']" />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Hora de apertura</div>
              <q-input dense borderless clearable type="time" v-model="comunArea.timeFrom" class="form__inputsR mt-1"
                color="primary" :rules="[val => !!val || 'Hora requerida']" />
            </div>

            <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Hora de cierre</div>
              <q-input dense borderless clearable type="time" v-model="comunArea.timeTo" class="form__inputsR mt-1"
                color="primary" :rules="[val => !!val || 'Hora requerida']" />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Días NO disponibles</div>
              <q-select dense borderless multiple v-model="comunArea.notAvailable" :options="dayNotAvailable"
                class="form__inputsR mt-1 bg-white" clearable use-chips />
            </div>

          </div>
        </Transition>

        <Transition name="horizontal">
          <div class="w-full" style="height:85%; overflow:hidden" v-if="step == 2">
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
                <div v-for="(rule, index) in comunArea.rulesList" :key="index"
                  class="row w-full relative-position md:mt-14 pb-8 mt-5" style="">

                  <q-btn v-if="comunArea.rulesList.length > 1" icon="eva-trash-2-outline" color="negative" dense outline
                    round @click="removeRule(index)" class="absolute-top-right"
                    style="top: -15px; right: 5px; z-index: 10;" />
                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">Título de la regla</div>
                    <q-input dense borderless clearable v-model="rule.title" class="form__inputsR mt-1 bg-white"
                      color="primary" :rules="[val => !!val || 'El título es requerido']" />
                  </div>
                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">
                      N° del articulo
                    </div>
                    <q-input dense borderless clearable v-model="rule.code" class="form__inputsR mt-1 bg-white"
                      color="primary" />
                  </div>

                  <div class="col-md-6 col-12 mt-1 px-2">
                    <div class="text-subtitle2 text-black">Nivel de severidad</div>
                    <q-select class="form__inputsR mt-1 bg-white" v-model="rule.severity" :options="severityOptions"
                      option-label="name" option-value="value" dense borderless />
                  </div>

                  <div class="col-md-6 col-12 mt-2 px-2">
                    <div class="text-subtitle2 text-black">Tipo de regla</div>
                    <q-select class="form__inputsR mt-1 bg-white" v-model="rule.type" :options="ruleTypeOptions"
                      option-label="name" option-value="value" dense borderless />
                  </div>

                  <div class="col-md-6 col-12 mt-2 px-2" v-if="rule.type.value === 2">
                    <div class="text-subtitle2 text-black">Amonestación Monetaria</div>
                    <q-input dense borderless clearable type="number" v-model="rule.suggest_amount"
                      class="form__inputsR mt-1 bg-white" color="primary" hint="Monto a multar"
                      :rules="[val => !!val || 'El monto es requerido para este tipo']" />
                  </div>

                  <div class="col-12 mt-2 px-2">
                    <div class="text-subtitle2 text-black">Descripción de la regla</div>
                    <q-input borderless clearable type="textarea" rows="2" v-model="rule.description"
                      class="form__inputsR mt-1 bg-white" color="primary" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>

        <div class="w-full mb-2 px-2 md:px-12 flex justify-center pt-5 pb-12">
          <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="backButton()" v-if="step > 0" class="me-7">
            <div class="md:px-10 px-5 py-1">Volver</div>
          </q-btn>
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="md:px-10 px-10 py-1">{{ step === 2 ? 'Guardar' : 'Siguiente' }}</div>
          </q-btn>
        </div>

      </div>

      <div class="h-4/6 flex flex-center" v-else>
        <q-spinner color="primary" size="10rem" :thickness="5" />
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