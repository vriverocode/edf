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
const blockedDates = ref([])
const selectedDates = ref([])

const show = computed({
  get: () => props.modelValue,
  set: (val) => {
    emit('update:modelValue', val)
    if (!val) emit('closeModal')
  },
})

const toMonthDay = (value) => {
  const raw = String(value || '')
  if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) return raw.slice(5)
  if (/^\d{2}-\d{2}$/.test(raw)) return raw
  const m = moment(raw, ['YYYY-MM-DD', 'YYYY/MM/DD'], true)
  return m.isValid() ? m.format('MM-DD') : raw
}

const isMonthDayBlocked = (monthDay) => {
  return blockedDates.value.some((d) => toMonthDay(d) === monthDay)
}

const blockedDatesFormatted = computed(() => {
  return blockedDates.value
    .map((d) => {
      const monthDay = toMonthDay(d)
      const isRecurring = /^\d{2}-\d{2}$/.test(monthDay)
      if (isRecurring) {
        const label = moment(monthDay, 'MM-DD').format('DD [de] MMMM')
        return { raw: monthDay, label, recurring: true }
      }
      return {
        raw: d,
        label: moment(d, 'YYYY-MM-DD').format('dddd DD [de] MMMM [del] YYYY'),
        recurring: false,
      }
    })
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
    const datesToAdd = selectedDates.value.map((d) =>
      moment(d, 'YYYY/MM/DD').format('MM-DD')
    )
    await comunAreaStore.storeBlockedDates(props.comunArea.id, datesToAdd)
    Notify.create({
      color: 'positive',
      message: 'Fechas bloqueadas',
    })
    selectedDates.value = []
    await fetchBlockedDates()
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al bloquear fechas' })
  } finally {
    loading.value = false
  }
}

const removeDate = async (date) => {
  if (!props.comunArea?.id) return
  loading.value = true
  try {
    await comunAreaStore.deleteBlockedDate(props.comunArea.id, toMonthDay(date))
    Notify.create({ color: 'positive', message: 'Fecha desbloqueada' })
    await fetchBlockedDates()
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al desbloquear fecha' })
  } finally {
    loading.value = false
  }
}

const isBlocked = (date) => {
  const monthDay = moment(date, 'YYYY/MM/DD').format('MM-DD')
  return isMonthDayBlocked(monthDay)
}

const dateOptions = (date) => {
  return date >= moment().format('YYYY/MM/DD')
}

watch(show, (val) => {
  if (val) {
    selectedDates.value = []
    fetchBlockedDates()
  }
})
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
          Selecciona fechas para bloquearlas <strong>todos los años</strong> en el calendario
          de reservas de <strong>{{ comunArea?.name }}</strong>.
          <br />
          Ejemplo: si seleccionas el <strong>31 de octubre</strong>, se bloquearán todos los
          31 de octubre de todos los años.
        </div>

        <q-date
          v-model="selectedDates"
          multiple
          mask="YYYY/MM/DD"
          color="primary"
          class="w-full"
          :options="dateOptions"
          :navigation-min-year-month="moment().format('YYYY/MM')"
          :locale="{
            days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
            daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
            months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
            monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
            firstDayOfWeek: 1,
            pluralDay: 'Días'
          }"
        >
          <template v-slot:day="{ date, selected }">
            <div
              :class="{
                'bg-primary text-white rounded-full': selected,
                'bg-red-1 text-red-7 rounded-full': isBlocked(date) && !selected,
              }"
              style="min-width: 28px; min-height: 28px; display: flex; align-items: center; justify-content: center;"
            >
              {{ parseInt(date.split('/')[2]) }}
            </div>
          </template>
        </q-date>

        <div class="q-mt-sm">
          <q-btn
            unelevated
            no-caps
            color="primary"
            icon="eva-calendar-outline"
            label="Bloquear seleccionadas"
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

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <!-- <q-btn flat label="Cerrar" color="primary" @click="show = false" /> -->
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>
