<script setup>
import { ref, watch, computed } from 'vue'
import { useComunAreaStore } from '@/services/store/comunArea.store'
import { Notify } from 'quasar'
import moment from 'moment'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months:
    'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
      '_'
    ),
})

const props = defineProps({
  modelValue: Boolean,
  comunArea: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue', 'closeModal'])

const comunAreaStore = useComunAreaStore()
const loading = ref(false)
const fetching = ref(false)
const blockedDates = ref([])
const selectedDates = ref([])

const months = [
  { n: 1, name: 'Enero', days: 31 },
  { n: 2, name: 'Febrero', days: 29 },
  { n: 3, name: 'Marzo', days: 31 },
  { n: 4, name: 'Abril', days: 30 },
  { n: 5, name: 'Mayo', days: 31 },
  { n: 6, name: 'Junio', days: 30 },
  { n: 7, name: 'Julio', days: 31 },
  { n: 8, name: 'Agosto', days: 31 },
  { n: 9, name: 'Septiembre', days: 30 },
  { n: 10, name: 'Octubre', days: 31 },
  { n: 11, name: 'Noviembre', days: 30 },
  { n: 12, name: 'Diciembre', days: 31 },
]

const show = computed({
  get: () => props.modelValue,
  set: (val) => {
    emit('update:modelValue', val)
    if (!val) emit('closeModal')
  },
})

const normalizeDates = (value) =>
  Array.isArray(value) ? value.filter((d) => typeof d === 'string' && d !== '') : []

const toMonthDay = (value) => {
  const raw = String(value || '')
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw.slice(5)
  if (/^\d{2}-\d{2}$/.test(raw)) return raw
  const m = moment(raw, ['YYYY-MM-DD', 'YYYY/MM/DD'], true)
  return m.isValid() ? m.format('MM-DD') : ''
}

const makeMonthDay = (month, day) =>
  `${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`

const isMonthDayBlocked = (monthDay) => {
  if (!monthDay) return false
  return blockedDates.value.some((d) => toMonthDay(d) === monthDay)
}

const isSelected = (monthDay) => selectedDates.value.includes(monthDay)

const toggleDay = (month, day) => {
  const key = makeMonthDay(month, day)
  if (isMonthDayBlocked(key)) return
  const index = selectedDates.value.indexOf(key)
  if (index >= 0) {
    selectedDates.value.splice(index, 1)
  } else {
    selectedDates.value.push(key)
  }
}

const dayClass = (month, day) => {
  const key = makeMonthDay(month, day)
  if (isMonthDayBlocked(key)) return 'bg-red-1 text-red-7 cursor-not-allowed'
  if (isSelected(key)) return 'bg-primary text-white'
  return 'bg-grey-2 text-grey-9 hover:bg-grey-3'
}

const blockedDatesFormatted = computed(() => {
  return normalizeDates(blockedDates.value)
    .map((d) => {
      const monthDay = toMonthDay(d)
      if (!monthDay) return null
      if (/^\d{2}-\d{2}$/.test(monthDay)) {
        const m = moment(monthDay, 'MM-DD', true)
        return {
          raw: monthDay,
          label: m.isValid() ? m.format('DD [de] MMMM') : monthDay,
          recurring: true,
        }
      }
      const m = moment(d, 'YYYY-MM-DD', true)
      return {
        raw: d,
        label: m.isValid() ? m.format('dddd DD [de] MMMM [del] YYYY') : String(d),
        recurring: false,
      }
    })
    .filter((item) => item && item.label)
    .sort((a, b) => a.raw.localeCompare(b.raw))
})

const fetchBlockedDates = async () => {
  if (!props.comunArea?.id || fetching.value) return
  fetching.value = true
  loading.value = true
  try {
    const data = await comunAreaStore.getBlockedDates(props.comunArea.id)
    blockedDates.value = normalizeDates(data)
  } catch {
    blockedDates.value = []
  } finally {
    loading.value = false
    fetching.value = false
  }
}

const errorMessage = (err, fallback) =>
  typeof err === 'string' && err ? err : fallback

const addSelectedDates = async () => {
  if (!selectedDates.value.length || !props.comunArea?.id || loading.value) return
  loading.value = true
  try {
    const datesToAdd = [
      ...new Set(
        selectedDates.value.filter(
          (d) => typeof d === 'string' && /^\d{2}-\d{2}$/.test(d)
        )
      ),
    ]
    if (!datesToAdd.length) {
      Notify.create({ color: 'negative', message: 'Selecciona fechas válidas' })
      return
    }
    await comunAreaStore.storeBlockedDates(props.comunArea.id, datesToAdd)
    Notify.create({ color: 'positive', message: 'Fechas bloqueadas' })
    selectedDates.value = []
    await fetchBlockedDates()
  } catch (err) {
    Notify.create({ color: 'negative', message: errorMessage(err, 'Error al bloquear fechas') })
  } finally {
    loading.value = false
  }
}

const removeDate = async (date) => {
  const monthDay = toMonthDay(date)
  if (!props.comunArea?.id || !monthDay || loading.value) return
  loading.value = true
  try {
    await comunAreaStore.deleteBlockedDate(props.comunArea.id, monthDay)
    Notify.create({ color: 'positive', message: 'Fecha desbloqueada' })
    await fetchBlockedDates()
  } catch (err) {
    Notify.create({ color: 'negative', message: errorMessage(err, 'Error al desbloquear fecha') })
  } finally {
    loading.value = false
  }
}

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      selectedDates.value = []
      fetchBlockedDates()
    }
  }
)
</script>

<template>
  <q-dialog v-model="show" persistent>
    <q-card class="w-full" style="max-width: 520px; border-radius: 1rem;">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6 text-bold text-grey-9">Bloquear fechas recurrentes</div>
        <q-space />
        <q-btn icon="eva-close-outline" flat round dense @click="show = false" />
      </q-card-section>

      <q-card-section class="q-pt-md relative-position">
        <div class="text-caption text-grey-8 mb-3">
          Selecciona los días del año para bloquearlos <strong>todos los años</strong> en el
          calendario de reservas de <strong>{{ comunArea?.name }}</strong>.
          <br />
          Ejemplo: si seleccionas el <strong>31 de octubre</strong>, se bloquearán todos los
          31 de octubre de todos los años.
        </div>

        <!-- Calendario propio: todo el año, sin navegación -->
        <div class="border border-grey-3 rounded-borders q-pa-sm" style="max-height: 46vh; overflow-y: auto;">
          <div class="grid grid-cols-2 gap-3">
            <div v-for="month in months" :key="month.n">
              <div class="text-caption text-bold text-grey-9 mb-1">{{ month.name }}</div>
              <div class="grid grid-cols-7 gap-1">
                <button
                  v-for="day in month.days"
                  :key="day"
                  type="button"
                  class="h-7 w-full rounded text-xs flex items-center justify-center cursor-pointer transition-colors"
                  :class="dayClass(month.n, day)"
                  @click="toggleDay(month.n, day)"
                >
                  {{ day }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Leyenda -->
        <div class="flex items-center gap-3 mt-2 text-caption text-grey-7">
          <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-sm bg-primary" /> Seleccionado
          </span>
          <span class="flex items-center gap-1">
            <span class="inline-block w-3 h-3 rounded-sm bg-red-1 border border-red-400" /> Bloqueado
          </span>
          <span class="text-grey-6">Clic para seleccionar / quitar · Rojo: quitar desde la lista</span>
        </div>

        <div class="q-mt-sm">
          <q-btn
            unelevated
            no-caps
            color="primary"
            icon="eva-calendar-outline"
            :label="`Bloquear seleccionadas (${selectedDates.length})`"
            class="full-width"
            :loading="loading"
            :disable="!selectedDates.length"
            @click="addSelectedDates"
          />
        </div>

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
          <div v-else-if="!loading" class="text-caption text-grey-5 py-2 text-center">
            No hay fechas bloqueadas
          </div>
        </div>

        <q-inner-loading :showing="loading" color="primary" />
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md" />
    </q-card>
  </q-dialog>
</template>
