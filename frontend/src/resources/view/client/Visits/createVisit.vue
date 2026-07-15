<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useVisitStore } from '@//services/store/visits.store'

const router = useRouter()
const apartmentStore = useApartmentStore()
const visitStore = useVisitStore();
const loading = ref(false)
const hasNoApartments = ref(false)
const apartmentsOptions = ref([
  { id: 0, number: 'Selecciona un departamento', area: null },
])

const typeOptions = ref([
  { id: '', title: 'Selecciona el motivo de la visita' },
  { id: 1, title: '👤 Visita personal' },
  { id: 2, title: '📦 Entrega' },
  { id: 3, title: '🔧 Servicio técnico' },
  // { id: 4, title: '🏠 Airbnb' },
  { id: 5, title: '💬 Otro' },
])

const formData = ref({
  apartment: {
    id: 0,
    number: 'Selecciona un departamento',
  },
  fullname: '',
  dni: '',
  type: '',
  description: '',
  date: null,
  hour: null,
})

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000,
  })
}

const getApartmentsByUser = () => {
  apartmentStore
    .getApartmentByUser()
    .then((response) => {
      const allApartments = response.data || []
      const apartments = allApartments.filter(apt => apt.type == 1)
      hasNoApartments.value = apartments.length === 0

      if (apartments.length === 0) {
        apartmentsOptions.value = []
        return
      }

      apartmentsOptions.value = [
        { id: 0, number: 'Selecciona un departamento', area: null },
        ...apartments,
      ]

      if (apartments.length === 1) {
        formData.value.apartment = apartments[0]
      }
    })
    .catch(() => {
      hasNoApartments.value = true
      apartmentsOptions.value = []
    })
}

const handleSubmit = () => {
  const apartmentId = formData.value.apartment?.id
  if (!validateData(apartmentId)) {
    return
  }

  loading.value = true

  const payload = {
    departament_id: apartmentId,
    fullname: formData.value.fullname,
    dni: formData.value.dni,
    type: formData.value.type,
    description: formData.value.description || null,
    date: formData.value.date,
    hour: formData.value.hour,
  }

  visitStore.storeVisit(payload)
    .then((response) => {
      console.log(response)
      showNotify('positive', 'Visita completada')
      setTimeout(() => {
        loading.value = false
        router.go(-1)
      }, 800)
    })
    .catch(() => {
      showNotify('negative', 'Error al registrar visita')
    })
    .finally(() => {
      loading.value = false;
    })


}

const validateData = (apartmentId) => {
  if (!apartmentId || apartmentId === 0) {
    showNotify('negative', 'Selecciona un departamento')
    return false
  }
  if (!formData.value.fullname) {
    showNotify('negative', 'Ingresa el nombre completo del visitante')
    return false
  }
  // if (!formData.value.dni) {
  //   showNotify('negative', 'Ingresa el documento de identidad')
  //   return false
  // }
  if (!formData.value.type) {
    showNotify('negative', 'Selecciona el tipo de visita')
    return false
  }
  if (!formData.value.date) {
    showNotify('negative', 'Indica la fecha de la visita')
    return false
  }
  if (formData.value.date === moment().format('DD/MM/YYYY') && formData.value.hour) {
    const [h, m] = formData.value.hour.split(':')
    const selectedMinutes = parseInt(h) * 60 + parseInt(m)
    const nowMinutes = moment().hours() * 60 + moment().minutes()
    if (selectedMinutes < nowMinutes) {
      showNotify('negative', 'La hora seleccionada ya pasó. Elige una hora posterior.')
      return false
    }
  }
  return true
}
function visitTimeLimit(hr) {
 if (hr  >= 7  &&  hr <= 23) {
    return true
  }
  return false
}

const myLocale = {
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  firstDayOfWeek: 1,
  format24h: true,
  pluralDay: 'días'
}

onMounted(() => {
  getApartmentsByUser()
})
</script>

<template>
  <div class="md:px-20 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Registrar visita
    </div>

    <q-form @submit="handleSubmit">
      <div class="row w-full">
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Departamento
          </div>
          <q-select v-if="!hasNoApartments" borderless dense class="form__inputsCR mt-2" v-model="formData.apartment"
            option-value="id" option-label="number" :options="apartmentsOptions" behavior="menu">
            <template v-slot:option="scope">
              <q-item v-bind="scope.itemProps">
                <div class="w-full">
                  <div class="flex items-center justify-between w-full">
                    <div class="text-subtitle1" style="font-weight: 500">
                      {{ scope.opt.id != 0 ? '#' : '' }} {{ scope.opt.number }}
                    </div>
                    <div v-if="scope.opt.id != 0" class="text-positive text-subtitle2 pl-2">
                      Tu departamento
                    </div>
                  </div>
                  <div class="text-caption text-grey-6" v-if="scope.opt.id != 0 && scope.opt.area">
                    {{ scope.opt.area }} mt²
                  </div>
                </div>
              </q-item>
            </template>
          </q-select>
          <q-banner v-else class="rounded-borders q-mt-2 bg-warning">
            <template v-slot:avatar>
              <q-icon name="eva-home-outline" color="warning" />
            </template>
            No tienes departamentos asignados. Contacta al administrador.
          </q-banner>
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Nombre completo del visitante
          </div>
          <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.fullname"
            :rules="[
              (val) =>
                (val && val.length > 0) ||
                'El nombre del visitante es requerido',
            ]" />
        </div>

        <!-- <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Documento de identidad
          </div>
          <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.dni" :rules="[
            (val) =>
              (val && val.length > 0) ||
              'El documento de identidad es requerido',
          ]" />
        </div> -->

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Tipo de visita
          </div>
          <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.type" option-value="id"
            option-label="title" :options="typeOptions" emit-value map-options behavior="menu" />
        </div>

        <div class="col-12 col-md-6 md:my-0 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Motivo o empresa (opcional)
          </div>
          <q-input borderless clearable type="textarea" autogrow dense class="form__inputsCR mt-2" color="primary"
            v-model="formData.description" placeholder="Ej. Servicio técnico de internet" />
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Fecha de ingreso
          </div>
          <q-input borderless dense class="form__inputsCR mt-2" v-model="formData.date" mask="date" :rules="['date']">
            <template v-slot:append>
              <q-icon name="eva-calendar-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="formData.date" minimal :locale="myLocale"
                    :options="(date) => date.replace(/\//g, '-') >= new Date().toISOString().split('T')[0]">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Cerrar" color="primary" flat />
                    </div>
                  </q-date>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black md:mt-2">
            Hora aproximada de ingreso
          </div>
          <q-input borderless dense class="form__inputsCR mt-2" v-model="formData.hour" mask="time">
            <template v-slot:append>
              <q-icon name="eva-clock-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-time v-model="formData.hour" format24h :options="visitTimeLimit"  >
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="Cerrar" color="primary" flat />
                    </div>
                  </q-time>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <div class="col-12 my-4 px-2 md:px-12 flex items-center justify-between">
          <q-btn flat color="grey-9" class="q-mr-sm" @click="router.push('/client/visit/list')">
            Volver
          </q-btn>
          <q-btn color="primary" style="border-radius: 0.5rem" type="submit" :loading="loading"
            :disable="hasNoApartments">
            <div class="px-10 py-1">
              Registrar visita
            </div>
          </q-btn>
        </div>
      </div>
    </q-form>
  </div>
</template>

<style lang="scss">
.form__inputsCR {
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}

@media (max-width: 780px) {
  .form__inputsCR {
    & .q-field__inner {
      padding: 0px 1rem;
    }
  }
}
</style>
