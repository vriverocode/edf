<script setup>
import { ref, watch } from 'vue'

const emit = defineEmits(['closeModal', 'updateList'])

const props = defineProps({
  dialog: Boolean,
  years: {
    type: Array,
    default: () => []
  },
  selectedYears: {
    type: Array,
    default: () => []
  }
})

const loading = ref(false)
const dialog = ref(props.dialog)
const yearsSelected = ref([...props.selectedYears])

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
})

watch(() => props.selectedYears, (newValue) => {
  yearsSelected.value = [...(newValue || [])]
})

const hideModal = () => {
  emit('closeModal')
}

const resetFilters = () => {
  yearsSelected.value = []
}

const applyFilters = () => {
  loading.value = true
  try {
    emit('closeModal')
    emit('updateList', { years: [...yearsSelected.value] })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-dialog v-model="dialog" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public">
      <div class="header-sectionModal" style="border-bottom: 1px solid lightgray;">
        <div class="flex justify-between items-center pr-5 pl-2 py-2">
          <q-btn round outline icon="eva-arrow-back-outline" color="primary" @click="resetFilters(), hideModal()" />
          <div class="text-2xl text-primary font-bold pt-1">
            Filtrar
          </div>
        </div>
      </div>

      <div class="content-sectionModal">
        <section class="content__modalSectionRifa md:mt-0 mt-0 py-2">
          <div class="row pt-3 pb-2 px-5">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Años
            </div>

            <div class="col-12" v-if="(years || []).length > 0">
              <div class="row">
                <div
                  v-for="year in years"
                  :key="year"
                  class="col-12 my-2 flex justify-between items-center"
                >
                  <div class="text-body1 text-black" style="font-weight: 500;">
                    {{ year }}
                  </div>
                  <q-checkbox v-model="yearsSelected" :val="year" color="primary" />
                </div>
              </div>
            </div>

            <div class="col-12" v-else>
              <div class="text-body2 text-grey-7">
                No hay años disponibles.
              </div>
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
              style="border-radius: 0.8rem; padding: 0px 2rem !important; font-size: 1rem;"
              @click="resetFilters()"
            />
            <q-btn
              label="Aplicar"
              unelevated
              class="q-mx-sm"
              color="primary"
              style="border-radius: 0.8rem; padding: 0px 2rem !important; font-size: 1rem;"
              :loading="loading"
              @click="applyFilters"
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

@media (max-width: 780px) {
  .filterDialog {
    & .dialog_document {
      max-height: 100% !important;
    }
  }
}
</style>

