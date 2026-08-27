<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  readings: {
    type: Array,
    default: () => []
  },
  totalConsumption: {
    type: Number,
    default: 0
  },
  currentMonth: {
    type: Number,
    default: () => new Date().getMonth() + 1
  },
  currentYear: {
    type: Number,
    default: () => new Date().getFullYear()
  }
})

const emit = defineEmits(['closeModal'])

const dialogVisible = ref(props.dialog)

const monthOptions = [
  { value: 1, name: 'Enero' },
  { value: 2, name: 'Febrero' },
  { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' },
  { value: 5, name: 'Mayo' },
  { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' },
  { value: 8, name: 'Agosto' },
  { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' },
  { value: 11, name: 'Noviembre' },
  { value: 12, name: 'Diciembre' }
]

const formatNumber = (value) => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0'
  return n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const close = () => {
  emit('closeModal')
}

watch(
  () => props.dialog,
  (open) => {
    dialogVisible.value = open
  }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" @hide="close">
    <q-card style="min-width: min(560px, 92vw); max-height: 80vh;" class="q-pa-md">
      <div class="text-h6 q-mb-sm">Lecturas de agua</div>
      <div class="text-caption text-grey-7 q-mb-md">
        Detalle de lecturas registradas para el periodo seleccionado.
      </div>

      <div class="row q-col-gutter-sm q-mb-md">
        <div class="col-6">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select
            :model-value="props.currentMonth"
            :options="monthOptions"
            option-label="name"
            option-value="value"
            emit-value
            map-options
            dense
            borderless
            class="form__inputsR mt-1"
            color="primary"
            disable
          />
        </div>
        <div class="col-6">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input
            :model-value="props.currentYear"
            dense
            borderless
            type="number"
            class="form__inputsR mt-1"
            color="primary"
            disable
          />
        </div>
      </div>

      <div v-if="props.readings.length === 0" class="text-center q-py-lg">
        <div class="text-caption text-grey-7">
          No hay lecturas de agua registradas para este periodo.
        </div>
      </div>

      <div v-else>
        <div class="readings-list" style="max-height: 350px; overflow-y: auto;">
          <div class="row items-center text-caption text-weight-bold text-grey-8 q-pa-sm q-mb-xs readings-header">
            <div class="col-4">Departamento</div>
            <div class="col text-center">Lect. anterior</div>
            <div class="col text-center">Lect. actual</div>
            <div class="col text-center">Consumo (m³)</div>
          </div>

          <div
            v-for="(reading, index) in props.readings"
            :key="index"
            class="reading-item row items-center q-pa-sm q-mb-xs"
          >
            <div class="col-4 text-body2 text-black text-weight-medium">
              {{ reading.department_name }}
            </div>
            <div class="col text-center text-body2 text-grey-7">
              {{ formatNumber(reading.previous_reading) }}
            </div>
            <div class="col text-center text-body2 text-grey-7">
              {{ formatNumber(reading.current_reading) }}
            </div>
            <div class="col text-center text-body2 text-weight-bold" style="color: #18181b;">
              {{ formatNumber(reading.consumption) }}
            </div>
          </div>
        </div>

        <div class="total-box q-mt-md q-pa-sm">
          <div class="row items-center">
            <div class="text-subtitle2 text-black">Total consumido:</div>
            <q-space />
            <div class="text-h6 text-weight-bold" style="color: #18181b;">
              {{ formatNumber(props.totalConsumption) }} m³
            </div>
          </div>
        </div>
      </div>

      <div class="row justify-end q-gutter-sm q-mt-lg">
        <q-btn flat label="Cerrar" color="grey" no-caps @click="close" />
      </div>
    </q-card>
  </q-dialog>
</template>

<style lang="scss" scoped>
.form__inputsR {
  & :deep(.q-field__inner) {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

.readings-header {
  border-bottom: 1px solid #e4e4e7;
}

.reading-item {
  border: 1px solid rgb(223, 223, 223);
  border-radius: 0.5rem;
  transition: all 0.15s ease;

  &:hover {
    background-color: #f4f4f5;
  }
}

.total-box {
  background-color: #f4f4f5;
  border: 1px solid #e4e4e7;
  border-radius: 0.5rem;
}
</style>
