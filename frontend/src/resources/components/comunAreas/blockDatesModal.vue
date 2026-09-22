<script setup>
import { ref, watch, computed } from 'vue'
import { useComunAreaStore } from '@/services/store/comunArea.store'
import { Notify } from 'quasar'
import moment from 'moment'

const props = defineProps({
  modelValue: Boolean,
  comunArea: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'updated'])

const comunAreaStore = useComunAreaStore()
const loading = ref(false)
const blockedDates = ref([])
const selectedDates = ref([])

const show = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const predefinedHolidays = [
  { date: '01-01', label: 'Año Nuevo' },
  { date: '01-05', label: 'Día del Trabajador' },
  { date: '07-06', label: 'San Pedro y San Pablo' },
  { date: '28-07', label: 'Fiestas Patrias' },
  { date: '29-06', label: 'San Pedro y San Pablo (obs)' },
  { date: '30-08', label: 'Santa Rosa de Lima' },
  { date: '08-10', label: 'Combate de Angamos' },
  { date: '01-11', label: 'Todos los Santos' },
  { date: '08-12', label: 'Inmaculada Concepción' },
  { date: '24-12', label: 'Nochebuena' },
  { date: '25-12', label: 'Navidad' },
  { date: '31-12', label: 'Año Nuevo (Noche)' },
]

const blockedDatesFormatted = computed(() => {
  return blockedDates.value
    .map(d => ({
      raw: d,
      label: moment(d, 'YYYY-MM-DD').format('dddd DD [de] MMMM [del] YYYY'),
    }))
    .sort((a, b) => a.raw.localeCompare(b.raw))
})

const fetchBlockedDates = async () => {
  if (!props.comunArea?.id) return
  loading.value = true
  try {
    const res = await comunAreaStore.getBlockedDates(props.comunArea.id)
    blockedDates.value = res.data || []
  } catch {
    blockedDates.value = []
  } finally {
    loading.value = false
  }
}

const addSelectedDates = async () => {
  if (!selectedDates.value.length || !props.comunArea?.id) return
  loading.value = true
  try {
    const datesToAdd = selectedDates.value.map(d => moment(d, 'YYYY/MM/DD').format('YYYY-MM-DD'))
    await comunAreaStore.storeBlockedDates(props.comunArea.id, datesToAdd)
    Notify.create({ color: 'positive', message: 'Fechas bloqueadas correctamente' })
    selectedDates.value = []
    await fetchBlockedDates()
    emit('updated')
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al bloquear fechas' })
  } finally {
    loading.value = false
  }
}

const addPredefinedHolidays = async () => {
  if (!props.comunArea?.id) return
  const currentYear = moment().year()
  const dates = predefinedHolidays.map(h => `${currentYear}-${h.date}`)
  loading.value = true
  try {
    await comunAreaStore.storeBlockedDates(props.comunArea.id, dates)
    Notify.create({ color: 'positive', message: 'Feriados predefinidos agregados' })
    await fetchBlockedDates()
    emit('updated')
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al agregar feriados' })
  } finally {
    loading.value = false
  }
}

const removeDate = async (date) => {
  if (!props.comunArea?.id) return
  loading.value = true
  try {
    await comunAreaStore.deleteBlockedDate(props.comunArea.id, date)
    Notify.create({ color: 'positive', message: 'Fecha desbloqueada' })
    await fetchBlockedDates()
    emit('updated')
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al desbloquear fecha' })
  } finally {
    loading.value = false
  }
}

const isBlocked = (date) => {
  return blockedDates.value.includes(moment(date, 'YYYY/MM/DD').format('YYYY-MM-DD'))
}

const dateOptions = (date) => {
  return date >= moment().format('YYYY/MM/DD')
}

watch(show, (val) => {
  if (val) {
    fetchBlockedDates()
    selectedDates.value = []
  }
})
</script>

<template>
  <q-dialog v-model="show" persistent>
    <q-card class="w-full" style="max-width: 520px; border-radius: 1rem;">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6 text-bold text-grey-9">Bloquear días feriados</div>
        <q-space />
        <q-btn icon="eva-close-outline" flat round dense @click="show = false" />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <div class="text-caption text-grey-6 mb-3">
          Selecciona fechas para bloquearlas en el calendario de reservas de <strong>{{ comunArea?.name }}</strong>.
        </div>

        <!-- Calendario -->
        <q-date
          v-model="selectedDates"
          multiple
          mask="YYYY/MM/DD"
          color="deep-orange"
          class="w-full"
          :options="dateOptions"
          :navigation-min-year-month="moment().format('YYYY/MM')"
          :locale="{
            days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
            daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
            months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
            monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
            firstDayOfWeek: 1,
          }"
        >
          <template v-slot:day="{ date, selected }">
            <div
              :class="{
                'bg-deep-orange text-white rounded-full': selected,
                'bg-red-1 text-red-7 rounded-full': isBlocked(date) && !selected,
              }"
              style="min-width: 28px; min-height: 28px; display: flex; align-items: center; justify-content: center;"
            >
              {{ parseInt(date.split('/')[2]) }}
            </div>
          </template>
        </q-date>

        <!-- Botones de acción -->
        <div class="flex q-mt-sm q-gutter-sm">
          <q-btn
            unelevated
            no-caps
            color="deep-orange"
            icon="eva-calendar-outline"
            label="Agregar seleccionadas"
            class="flex-1"
            :loading="loading"
            :disable="!selectedDates.length"
            @click="addSelectedDates"
          />
          <q-btn
            outline
            no-caps
            color="orange"
            icon="eva-star-outline"
            label="Feriados del año"
            :loading="loading"
            @click="addPredefinedHolidays"
          />
        </div>

        <!-- Lista de fechas bloqueadas -->
        <div class="q-mt-md">
          <div class="text-subtitle2 text-grey-8 q-mb-sm">
            Fechas bloqueadas ({{ blockedDatesFormatted.length }})
          </div>
          <div v-if="blockedDatesFormatted.length" class="q-gutter-xs">
            <q-chip
              v-for="item in blockedDatesFormatted"
              :key="item.raw"
              removable
              color="red-1"
              text-color="red-7"
              dense
              @remove="removeDate(item.raw)"
            >
              {{ item.label }}
            </q-chip>
          </div>
          <div v-else class="text-caption text-grey-5 py-2 text-center">
            No hay fechas bloqueadas
          </div>
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat label="Cerrar" color="grey-7" @click="show = false" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>
