<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useReserveStore } from '@/services/store/reserve.store'
import { Notify } from 'quasar'
import moment from 'moment'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const route = useRoute()
const router = useRouter()
const reserveStore = useReserveStore()

const loading = ref(false)
const submitting = ref(false)
const booking = ref(null)
const area = ref(null)
const slots = ref([])
const selectedSlot = ref(null)

const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const getExtensionSlots = () => {
  loading.value = true
  reserveStore.getExtensionSlots(route.params.id)
    .then((response) => {
      booking.value = response.data.booking
      area.value = response.data.area
      slots.value = response.data.slots
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al cargar horarios disponibles')
      router.back()
    })
    .finally(() => {
      loading.value = false
    })
}

const selectSlot = (slot) => {
  selectedSlot.value = slot
}

const extensionCost = () => {
  if (!selectedSlot.value || !area.value) return 0
  return (area.value.extension_price || 0) * selectedSlot.value.duration
}

const confirmExtension = () => {
  if (!selectedSlot.value) {
    showNotify('negative', 'Selecciona un horario para extender')
    return
  }

  submitting.value = true
  reserveStore.createExtension({
    booking_id: booking.value.id,
    time_from: selectedSlot.value.time_from,
    time_to: selectedSlot.value.time_to,
  })
    .then((response) => {
      showNotify('positive', 'Extensión registrada con éxito')
      if (response.data.toPay) {
        router.push('/client/reserves/pay-reserve/' + response.data.id)
      } else {
        router.push('/client/reserves/list')
      }
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al crear la extensión')
    })
    .finally(() => {
      submitting.value = false
    })
}

const goBack = () => {
  router.back()
}

onMounted(() => {
  getExtensionSlots()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Contenido -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Header info de la reserva actual -->
        <div class="text-center mb-6">
          <q-icon name="eva-clock-outline" size="3.5rem" color="primary" />
          <div class="text-h6 text-primary font-bold mt-2">Extender Reserva</div>
          <div class="text-caption text-grey-7">Selecciona el horario adicional para tu reserva</div>
        </div>

        <!-- Card info reserva actual -->
        <div class="selectedDateBlock px-4 py-3 mb-4" v-if="booking">
          <div class="flex items-center justify-between">
            <div class="flex items-center">
              <div class="w-12 h-12 rounded-xl flex items-center justify-center mr-3" style="background: #e8f0fe;">
                <img :src="urlMedia + '/images/icons/' + (area?.icon || 'default') + '.svg'"
                  style="height: 100%" alt="">
              </div>
              <div>
                <div class="text-bold" style="font-size: 1rem;">{{ area?.name }}</div>
                <div class="text-grey-7" style="font-size: 0.8rem;">{{ moment(booking.date).format('dddd DD [de] MMMM') }}</div>
              </div>
            </div>
            <q-chip color="positive" text-color="white" size="0.75rem">
              <div style="font-size: 0.7rem;">{{ booking.status_label }}</div>
            </q-chip>
          </div>
          <div class="mt-2 pt-2" style="border-top: 1px dashed #ccc;">
            <div class="flex items-center text-grey-8">
              <q-icon name="eva-clock-outline" size="1rem" class="mr-2" />
              <span class="text-bold" style="font-size: 0.95rem;">
                {{ booking.time_from }} - {{ booking.time_to }}
              </span>
              <span class="text-grey-6 ml-2" style="font-size: 0.8rem;">
                ({{ booking.booking_hour }} hrs)
              </span>
            </div>
          </div>
        </div>

        <!-- Slots disponibles -->
        <div v-if="slots.length > 0">
          <div class="text-subtitle1 text-bold text-grey-9 mb-2">Horarios disponibles</div>
          <div class="text-caption text-grey-6 mb-3">
            Elige el horario adyacente que deseas agregar a tu reserva
          </div>

          <div v-for="(slot, index) in slots" :key="index"
            class="extensionSlot mb-3 px-4 py-3 cursor-pointer"
            :class="{ 'extensionSlot--selected': selectedSlot?.time_from === slot.time_from }"
            @click="selectSlot(slot)">
            <div class="flex items-center justify-between">
              <div class="flex items-center">
                <div class="extensionSlot__radio mr-3"
                  :class="{ 'extensionSlot__radio--active': selectedSlot?.time_from === slot.time_from }">
                </div>
                <div>
                  <div class="text-bold" style="font-size: 1rem;">
                    {{ slot.position === 'before' ? 'Horario anterior' : 'Horario siguiente' }}
                  </div>
                  <div class="text-primary text-bold mt-1" style="font-size: 1.1rem;">
                    {{ slot.time_from }} - {{ slot.time_to }}
                  </div>
                  <div class="text-grey-6 mt-1" style="font-size: 0.78rem;">
                    {{ slot.duration }} hora(s) adicional(es)
                  </div>
                </div>
              </div>
              <div class="text-right">
                <q-chip :color="slot.status === 'Disponible' ? 'positive' : 'warning'" text-color="white" size="0.7rem">
                  {{ slot.status }}
                </q-chip>
              </div>
            </div>
          </div>
        </div>

        <!-- Sin slots -->
        <div v-else-if="!loading" class="text-center py-10">
          <q-icon name="eva-alert-circle-outline" size="4rem" color="grey-5" />
          <div class="text-h6 text-grey-7 mt-2">Sin horarios disponibles</div>
          <div class="text-caption text-grey-6">
            No hay horarios adyacentes disponibles para extender tu reserva
          </div>
        </div>

        <!-- Resumen de extensión -->
        <div v-if="selectedSlot" class="selectedDateBlock px-4 py-3 mt-4">
          <div class="text-bold mb-2" style="font-size: 1rem;">Resumen de extensión</div>
          <div class="flex justify-between items-center mb-1">
            <span class="text-grey-7">Horario adicional</span>
            <span class="text-bold">{{ selectedSlot.time_from }} - {{ selectedSlot.time_to }}</span>
          </div>
          <div class="flex justify-between items-center mb-1">
            <span class="text-grey-7">Duración</span>
            <span class="text-bold">{{ selectedSlot.duration }} hora(s)</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-grey-7">Costo extensión</span>
            <span class="text-bold text-primary">S/ {{ extensionCost().toFixed(2) }}</span>
          </div>
          <div v-if="area?.extension_price" class="text-caption text-grey-6 mt-1">
            (S/ {{ area.extension_price.toFixed(2) }} x hora)
          </div>
        </div>
      </div>
    </div>

    <!-- Botones inferiores -->
    <div class="px-4 py-3" style="min-height: 10%;">
      <div class="row" v-if="!loading && slots.length > 0">
        <div class="col-4 flex flex-center">
          <q-btn outline color="grey-8" unelevated no-caps style="width: 90%; border-radius: 3rem;" @click="goBack">
            <div>Volver</div>
          </q-btn>
        </div>
        <div class="col-8 flex flex-center">
          <q-btn color="primary" unelevated no-caps style="width: 95%; border-radius: 3rem;"
            :disable="!selectedSlot" :loading="submitting" @click="confirmExtension">
            <div class="font-bold">
              Confirmar extensión
              <span v-if="selectedSlot"> - S/ {{ extensionCost().toFixed(2) }}</span>
            </div>
          </q-btn>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.selectedDateBlock {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;
}

.extensionSlot {
  border: 2px solid lightgray;
  border-radius: 0.8rem;
  transition: all 0.2s ease-in;

  &:hover {
    border-color: #72b9af;
    background: #f8f9fa;
  }

  &--selected {
    border: 2px solid #72b9af;
    background: #e8f5f2;
  }

  &__radio {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid #bbb;
    border-radius: 50%;
    transition: all 0.2s ease;

    &--active {
      border-color: #5571b7;
      background: #5571b7;
      box-shadow: inset 0 0 0 3px white;
    }
  }
}
</style>
