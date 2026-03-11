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
  { id: 0, number: 'Selecciona un apartamento', area: null },
])

const typeOptions = ref([
  { id: '', title: 'Selecciona el tipo de visita' },
  { id: 1, title: 'Personal' },
  { id: 2, title: 'Entrega' },
  { id: 3, title: 'Servicio' },
  { id: 4, title: 'Otro' },
])

const formData = ref({
  apartment: {
    id: 0,
    number: 'Selecciona un apartamento',
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
      const apartments = response.data || []
      hasNoApartments.value = apartments.length === 0

      if (apartments.length === 0) {
        apartmentsOptions.value = []
        return
      }

      apartmentsOptions.value = [
        { id: 0, number: 'Selecciona un apartamento', area: null },
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
    showNotify('negative', 'Selecciona un apartamento')
    return false
  }
  if (!formData.value.fullname) {
    showNotify('negative', 'Ingresa el nombre completo del visitante')
    return false
  }
  if (!formData.value.dni) {
    showNotify('negative', 'Ingresa el documento de identidad')
    return false
  }
  if (!formData.value.type) {
    showNotify('negative', 'Selecciona el tipo de visita')
    return false
  }
  if (!formData.value.date) {
    showNotify('negative', 'Indica la fecha de la visita')
    return false
  }
  return true
}

onMounted(() => {
  getApartmentsByUser()
})
</script>

<template>
  <div class="md:px-20 md:mx-16 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Registrar visita
    </div>

    <q-form @submit="handleSubmit">
      <div class="row w-full">
        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Apartamento
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
                      Tu apartamento
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
            No tienes apartamentos asignados. Contacta al administrador.
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

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Documento de identidad
          </div>
          <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.dni" :rules="[
            (val) =>
              (val && val.length > 0) ||
              'El documento de identidad es requerido',
          ]" />
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Tipo de visita
          </div>
          <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.type" option-value="id"
            option-label="title" :options="typeOptions" emit-value map-options behavior="menu" />
        </div>

        <div class="col-12 col-md-6 md:my-0 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Motivo / descripción (opcional)
          </div>
          <q-input borderless clearable type="textarea" autogrow dense class="form__inputsCR mt-2" color="primary"
            v-model="formData.description" />
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black pt-2">
            Fecha de la visita
          </div>
          <q-input borderless dense class="form__inputsCR mt-2" v-model="formData.date" mask="date" :rules="['date']">
            <template v-slot:append>
              <q-icon name="eva-calendar-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-date v-model="formData.date" minimal
                    :options="(date) => date.replace(/\//g, '-') >= new Date().toISOString().split('T')[0]">
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="OK" color="primary" flat />
                    </div>
                  </q-date>
                </q-popup-proxy>
              </q-icon>
            </template>
          </q-input>
        </div>

        <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black md:mt-2">
            Hora aproximada de llegada
          </div>
          <q-input borderless dense class="form__inputsCR mt-2" v-model="formData.hour" mask="time">
            <template v-slot:append>
              <q-icon name="eva-clock-outline" class="cursor-pointer">
                <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                  <q-time v-model="formData.hour" format24h>
                    <div class="row items-center justify-end">
                      <q-btn v-close-popup label="OK" color="primary" flat />
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
