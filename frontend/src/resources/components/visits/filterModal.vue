<script setup>
import { ref, watch } from 'vue'

const emit = defineEmits(['closeModal', 'updateList'])

const props = defineProps({
  dialog: Boolean,
  currentFilters: {
    type: Object,
    default: () => ({ search: '', status: [], departament_id: '', date_from: '', date_to: '' }),
  },
  title: {
    type: String,
    default: 'Filtrar visitas',
  },
  searchLabel: {
    type: String,
    default: 'Buscar',
  },
  statusOptions: {
    type: Array,
    default: () => [],
  },
  apartmentOptions: {
    type: Array,
    default: () => [],
  },
})

const dialog = ref(props.dialog)
const filters = ref({
  search: '',
  status: [],
  departament_id: '',
  date_from: '',
  date_to: '',
})

const syncFilters = () => {
  filters.value = {
    search: props.currentFilters?.search || '',
    status: props.currentFilters?.status || [],
    departament_id: props.currentFilters?.departament_id || '',
    date_from: props.currentFilters?.date_from || '',
    date_to: props.currentFilters?.date_to || '',
  }
}

const hideModal = () => {
  emit('closeModal')
}

const resetFilters = () => {
  filters.value = { search: '', status: [], departament_id: '', date_from: '', date_to: '' }
}

const updateList = () => {
  emit('closeModal')
  emit('updateList', { ...filters.value })
}

watch(
  () => props.dialog,
  (newValue) => {
    dialog.value = newValue
    if (newValue) {
      syncFilters()
    }
  }
)
</script>

<template>
  <q-dialog v-model="dialog" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public">
      <div class="header-sectionModal" style="border-bottom: 1px solid lightgray;">
        <div class="flex justify-between items-center pr-5 pl-2 py-2">
          <q-btn round outline icon="eva-arrow-back-outline" color="primary" @click="hideModal" />
          <div class="text-2xl text-primary font-bold pt-1">
            {{ title }}
          </div>
        </div>
      </div>
      <div class="content-sectionModal">
        <section class="content__modalSectionRifa md:mt-0 mt-0 py-2">
          <div class="row pt-3 pb-4 px-5">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Busqueda
            </div>
            <div class="col-12 pb-2">
              <q-input class="form__inputsFilterBookings" v-model="filters.search" dense borderless clearable
                :label="searchLabel">
                <template #prepend>
                  <q-icon name="eva-search-outline" />
                </template>
              </q-input>
            </div>
          </div>
          <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Departamento
            </div>
            <div class="col-12 pb-2">
              <q-select class="form__inputsFilterBookings" v-model="filters.departament_id" :options="apartmentOptions"
                option-label="label" option-value="value" emit-value map-options clearable dense borderless
                label="Selecciona un departamento" />
            </div>
          </div>
          <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Estado
            </div>
            <div class="col-12">
              <q-option-group v-model="filters.status" class="group__status" :options="statusOptions" type="checkbox"
                color="primary" dense />
            </div>
          </div>
          <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Rango de fechas de llegada
            </div>
            <div class="col-12 col-md-6 pb-2">
              <q-input class="form__inputsFilterBookings" v-model="filters.date_from" dense borderless clearable
                label="Fecha desde" type="date" />
            </div>
            <div class="col-12 col-md-6 pb-2">
              <q-input class="form__inputsFilterBookings" v-model="filters.date_to" dense borderless clearable
                label="Fecha hasta" type="date" />
            </div>
          </div>


        </section>
        <section class="pb-5 pt-2" style="border-top: 1px solid lightgray;">
          <div class="flex justify-evenly mt-2">
            <q-btn label="Restablecer" unelevated class="q-mx-sm" color="primary" outline
              style="border-radius: 0.8rem; padding: 0px 2rem !important; font-size: 1rem;" @click="resetFilters" />
            <q-btn label="Aplicar" unelevated class="q-mx-sm" color="primary"
              style="border-radius: 0.8rem; padding: 0px 2rem !important; font-size: 1rem;" @click="updateList" />
          </div>
        </section>
      </div>
    </q-card>
  </q-dialog>
</template>

<style lang="scss">
.header-sectionModal {
  height: 8%;
  overflow: hidden;
}

.group__status {
  & .q-checkbox {
    margin-top: 0.5rem;
  }
}

.content-sectionModal {
  height: 92%;
  overflow: auto;
}

.filterDialog {
  margin-left: 0%;
  overflow: hidden;
  position: relative;

  & .dialog_document {
    width: 100%;
    border-radius: 0rem !important;
    height: 100%;
    overflow: hidden;
  }

  & .q-dialog__inner--minimized {
    padding: 0px;
  }
}

.form__inputsFilterBookings {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

@media (max-width: 780px) {
  .form__inputsFilterBookings {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}

@media (max-width: 780px) {
  .filterDialog {
    & .dialog_document {
      max-height: 100% !important;
    }
  }
}
</style>
