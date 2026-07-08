<script setup>
import { ref, watch, onMounted } from 'vue'
import { useComunAreaStore } from '@/services/store/comunArea.store'

const emit = defineEmits(['closeModal', 'updateList'])

const props = defineProps({
  dialog: Boolean,
  filters: Object,
})

const dialog = ref(props.dialog)
const localFilters = ref({ ...props.filters })
const areas = ref([])

watch(() => props.dialog, (val) => {
  dialog.value = val
})

watch(() => props.filters, (val) => {
  localFilters.value = { ...val }
}, { deep: true })

const hideModal = () => {
  emit('closeModal')
}

const updateList = () => {
  emit('closeModal')
  emit('updateList', { ...localFilters.value })
}

const resetFilters = () => {
  localFilters.value = {
    status: 4,
    area_id: null,
    date_from: null,
    date_to: null,
    sort_by: 'created_at',
    sort_dir: 'desc',
  }
}

const statusOptions = [
  { label: 'Todos', value: 4 },
  { label: 'Cancelada', value: 0 },
  { label: 'Pago pendiente', value: 1 },
  { label: 'Pendiente de aprob.', value: 2 },
  { label: 'Exitoso', value: 3 },
]

const sortByOptions = [
  { label: 'Fecha de creación', value: 'created_at' },
  { label: 'Fecha reserva', value: 'date' },
  { label: 'Estado', value: 'status' },
  { label: 'Monto', value: 'amount' },
]

const sortDirOptions = [
  { label: 'Descendente', value: 'desc' },
  { label: 'Ascendente', value: 'asc' },
]

const loadAreas = () => {
  useComunAreaStore().getAllComunAreas()
    .then((data) => {
      if (data.code !== 200) return
      areas.value = (data.data || []).map((a) => ({ label: a.name, value: a.id }))
    })
    .catch(() => { areas.value = [] })
}

onMounted(() => {
  loadAreas()
})
</script>

<template>
  <q-dialog v-model="dialog" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document">
      <div class="header-sectionModal" style="border-bottom: 1px solid lightgray">
        <div class="flex justify-between items-center pr-5 pl-2 py-2">
          <q-btn round outline icon="eva-arrow-back-outline" color="primary" @click="hideModal()" />
          <div class="text-2xl text-primary font-bold pt-1">Filtrar</div>
        </div>
      </div>

      <div class="content-sectionModal">
        <section class="content__modalSectionRifa py-2">
          <div class="row pt-3 pb-4 px-5">
            <div class="mb-1 text-lg font-medium text-primary col-12">Estado</div>
            <div class="col-12">
              <q-select
                v-model="localFilters.status"
                :options="statusOptions"
                emit-value
                map-options
                dense
                borderless
                class="form__inputsFilterBookings"
              />
            </div>
          </div>

          <div class="row pt-4 pb-5 px-5" style="border-top: 1px solid lightgray">
            <div class="mb-3 text-lg font-medium text-primary col-12">Área común</div>
            <div class="col-12">
              <q-select
                v-model="localFilters.area_id"
                :options="areas"
                emit-value
                map-options
                use-input
                input-debounce="200"
                label="Selecciona un área"
                clearable
                dense
                borderless
                class="form__inputsFilterBookings"
              />
            </div>
          </div>

          <div class="row pt-4 pb-5 px-5" style="border-top: 1px solid lightgray">
            <div class="mb-4 text-lg font-medium text-primary col-12">Rango de fechas</div>
            <div class="col-6 pr-1">
              <q-input
                v-model="localFilters.date_from"
                dense
                borderless
                type="date"
                label="Desde"
                class="form__inputsFilterBookings"
              />
            </div>
            <div class="col-6 pl-1">
              <q-input
                v-model="localFilters.date_to"
                dense
                borderless
                type="date"
                label="Hasta"
                class="form__inputsFilterBookings"
              />
            </div>
          </div>

          <div class="row py-4 px-5" style="border-top: 1px solid lightgray">
            <div class="mb-4 text-lg font-medium text-primary col-12">Ordenar por</div>
            <div class="col-6 pr-1">
              <q-select
                v-model="localFilters.sort_by"
                :options="sortByOptions"
                emit-value
                map-options
                dense
                borderless
                class="form__inputsFilterBookings"
              />
            </div>
            <div class="col-6 pl-1">
              <q-select
                v-model="localFilters.sort_dir"
                :options="sortDirOptions"
                emit-value
                map-options
                dense
                borderless
                class="form__inputsFilterBookings"
              />
            </div>
          </div>
        </section>

        <section class="pb-5">
          <div class="flex justify-evenly mt-2">
            <q-btn
              label="Restablecer"
              unelevated
              class="q-mx-sm"
              color="primary"
              outline
              style="border-radius: 0.8rem; padding: 0px 2rem; font-size: 1rem"
              @click="resetFilters()"
            />
            <q-btn
              label="Aplicar"
              unelevated
              class="q-mx-sm"
              color="primary"
              style="border-radius: 0.8rem; padding: 0px 2rem; font-size: 1rem"
              @click="updateList"
            />
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
  .filterDialog {
    & .dialog_document {
      max-height: 100% !important;
    }
  }
}
</style>
