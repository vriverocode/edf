<script setup>
import { ref, watch } from 'vue'

const emit = defineEmits(['closeModal', 'applyFilter'])

const props = defineProps({
  dialog: Boolean,
  currentFilters: {
    type: Object,
    default: () => ({
      month: null,
      year: new Date().getFullYear(),
      status: null,
      provider_id: null,
      category_id: null,
      date_from: null,
      date_to: null
    })
  },
  monthOptions: {
    type: Array,
    default: () => []
  },
  statusOptions: {
    type: Array,
    default: () => []
  },
  providerOptions: {
    type: Array,
    default: () => []
  },
  categoryOptions: {
    type: Array,
    default: () => []
  }
})

const dialog = ref(props.dialog)
const draftFilter = ref({ ...props.currentFilters })

const syncFilters = () => {
  draftFilter.value = { ...props.currentFilters }
}

const hideModal = () => {
  emit('closeModal')
}

const applyFilter = () => {
  emit('applyFilter', { ...draftFilter.value })
}

watch(
  () => props.dialog,
  (newValue) => {
    dialog.value = newValue
    if (newValue) syncFilters()
  }
)
</script>

<template>
  <q-dialog v-model="dialog" persistent>
    <q-card style="min-width: 320px; max-width: 90vw;">
      <q-card-section class="text-h6">Filtrar gastos</q-card-section>
      <q-card-section class="q-pt-none" style="max-height: 60vh; overflow-y: auto;">
        <div class="text-subtitle2 text-black mb-1">Mes</div>
        <q-select
          dense
          borderless
          class="form__inputsR mb-3"
          v-model="draftFilter.month"
          :options="monthOptions"
          option-label="name"
          option-value="value"
          emit-value
          map-options
        />
        <div class="text-subtitle2 text-black mb-1">Año</div>
        <q-input
          dense
          borderless
          class="form__inputsR mb-3"
          type="number"
          v-model.number="draftFilter.year"
        />
        <div class="text-subtitle2 text-black mb-1">Estado</div>
        <q-select
          dense
          borderless
          class="form__inputsR mb-3"
          v-model="draftFilter.status"
          :options="statusOptions"
          option-label="name"
          option-value="value"
          emit-value
          map-options
        />
        <div class="text-subtitle2 text-black mb-1">Proveedor</div>
        <q-select
          dense
          borderless
          clearable
          class="form__inputsR mb-3"
          v-model="draftFilter.provider_id"
          :options="providerOptions"
          option-label="label"
          option-value="value"
          emit-value
          map-options
        />
        <div class="text-subtitle2 text-black mb-1">Categoría</div>
        <q-select
          dense
          borderless
          clearable
          class="form__inputsR mb-3"
          v-model="draftFilter.category_id"
          :options="categoryOptions"
          option-label="label"
          option-value="value"
          emit-value
          map-options
        />
        <div class="row q-col-gutter-sm">
          <div class="col-6">
            <div class="text-subtitle2 text-black mb-1">Desde</div>
            <q-input dense borderless class="form__inputsR" type="date" v-model="draftFilter.date_from" />
          </div>
          <div class="col-6">
            <div class="text-subtitle2 text-black mb-1">Hasta</div>
            <q-input dense borderless class="form__inputsR" type="date" v-model="draftFilter.date_to" />
          </div>
        </div>
      </q-card-section>
      <q-card-actions align="right">
        <q-btn flat label="Cancelar" color="grey" @click="hideModal" />
        <q-btn flat label="Aplicar" color="primary" @click="applyFilter" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<style lang="scss">

.form__inputsR{
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
  &.q-field--auto-height.q-field--dense.q-field--labeled .q-field__control-container{
    padding-top: 10px!important;
  }
}
@media (max-width: 780px) {
  
.form__inputsR{
  & .q-field__inner {

    padding: 0.1rem 1rem;
  }
}
}

</style>