<script setup>
import { ref } from 'vue';
import { useIncidentStore } from '@/services/store/incident.store';
import { useRouter } from 'vue-router';
import { useQuasar } from 'quasar';
import moment from 'moment';

const router = useRouter();
const $q = useQuasar();
const incidentStore = useIncidentStore();

const form = ref({
  title: '',
  description: '',
  date: moment().format('YYYY-MM-DD'),
  hour: moment().format('HH:mm'),
  location: '',
  type: null
});

const typeOptions = incidentStore.typeLabels.map((label, index) => {
  return { label, value: index }
}).filter(opt => opt.label !== '');

const loading = ref(false);

const onSubmit = () => {
  loading.value = true;
  incidentStore.createIncident(form.value)
    .then(() => {
      $q.notify({
        type: 'positive',
        message: 'Incidencia reportada con éxito'
      });
      router.go(-1);
    })
    .catch((error) => {
      $q.notify({
        type: 'negative',
        message: error || 'Error al reportar la incidencia'
      });
    })
    .finally(() => {
      loading.value = false;
    });
}
</script>

<template>
  <div class="md:px-20 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Reportar Incidencia
    </div>

    <q-form @submit="onSubmit">
      <div class="row w-full">
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Título
          </div>
          <q-input
            v-model="form.title"
            borderless
            dense
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => val && val.length > 0 || 'Por favor ingresa un título']"
          />
        </div>

        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Tipo de Incidencia
          </div>
          <q-select
            v-model="form.type"
            :options="typeOptions"
            option-value="value"
            option-label="label"
            emit-value
            map-options
            borderless
            dense
            class="form__inputsCR mt-2"
            behavior="menu"
            lazy-rules
            :rules="[val => val !== null || 'Por favor selecciona un tipo']"
          />
        </div>

        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Fecha
          </div>
          <q-input
            v-model="form.date"
            type="date"
            borderless
            dense
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => !!val || 'Por favor selecciona una fecha']"
          />
        </div>

        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Hora
          </div>
          <q-input
            v-model="form.hour"
            type="time"
            borderless
            dense
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => !!val || 'Por favor selecciona una hora']"
          />
        </div>

        <div class="col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Ubicación (Opcional)
          </div>
          <q-input
            v-model="form.location"
            borderless
            dense
            clearable
            class="form__inputsCR mt-2"
            color="primary"
          />
        </div>

        <div class="col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Descripción detallada
          </div>
          <q-input
            v-model="form.description"
            type="textarea"
            borderless
            dense
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => val && val.length > 0 || 'Por favor ingresa una descripción']"
          />
        </div>

        <div class="col-12 pb-8 mt-6 px-2 md:px-12 flex items-center justify-between">
          <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
            <q-btn color="grey-9" style="border-radius: 0.5rem;" @click="router.go(-1)">
              <div class="px-8 py-1">
                Volver
              </div>
            </q-btn>
          </div>
          <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
            <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="px-8 py-1">
                Enviar
              </div>
            </q-btn>
          </div>
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
