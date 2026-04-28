<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWaterReadingsStore } from '@/services/store/waterReadings.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const waterReadingsStore = useWaterReadingsStore()

const reading = ref(null)
const loading = ref(false)
const error = ref(null)
const imageBroken = ref(false)

const readingId = computed(() => route.params.id || route.query.id)

const photoUrl = computed(() => {
  const r = reading.value
  if (!r) return ''
  return r.photo || r.meter_photo || r.proof_photo || ''
})

const consumptionM3 = computed(() => {
  const r = reading.value
  if (!r) return null
  const prev = Number(r.previous_reading)
  const cur = Number(r.current_reading)
  if (!Number.isFinite(prev) || !Number.isFinite(cur)) return null
  return Number((cur - prev).toFixed(2))
})

const subtotalSoles = computed(() => {
  const r = reading.value
  const cons = consumptionM3.value
  if (!r || cons === null) return null
  const price = Number(r.m3_price)
  if (!Number.isFinite(price)) return null
  return Number((cons * price).toFixed(2))
})

const getReadingById = async (id) => {
  try {
    loading.value = true
    error.value = null
    imageBroken.value = false
    const response = await waterReadingsStore.getWaterReadingById(id)
    reading.value = response.data
  } catch (err) {
    error.value = err || 'Error al cargar la medición de agua'
  } finally {
    loading.value = false
  }
}

const reload = () => {
  if (readingId.value) getReadingById(readingId.value)
}

const goToList = () => router.push('/admin/water_readings/list')
const goToEdit = () => router.push('/admin/water_readings/edit/' + readingId.value)

onMounted(() => {
  if (readingId.value) {
    getReadingById(readingId.value)
  } else {
    error.value = 'ID no proporcionado'
  }
})
</script>

<template>
  <div class=" relative overflow-hidden h-full">
    <div class="relative pt-4 pb-8 md:px-6 px-3 h-full" style="overflow: scroll;">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium">Cargando medición...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <div class="flex gap-3">
          <q-btn color="primary" outline @click="reload">Reintentar</q-btn>
          <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
        </div>
      </div>

      <div v-else-if="reading" class="flex flex-col items-center md:px-28 md:mx-28 ">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col w-full">
          <div class="row w-full mb-3 items-start">
            <div class="flex flex-col items-start col-md-8 col-7 md:pl-5 pl-3">
              <div class="mb-3 pt-5">
                <div class="text-2xl font-bold text-gray-900">
                  Dpt: {{ reading.departament?.number ?? reading.departament_id }} ·
                  {{ reading.month_label }} {{ reading.year }}
                </div>
                <div class="text-grey-7 mt-1">
                  Registrado: {{ moment(reading.created_at).format('DD/MM/YYYY HH:mm') }}
                </div>
                <div v-if="reading.departament?.owner?.name" class="text-grey-7 mt-1">
                  Propietario: {{ reading.departament.owner.name }}
                </div>
              </div>
            </div>
            <div class="col-md-4 col-5 text-right md:pr-5 pr-3 pt-5">
              <q-btn color="primary" class="rounded-lg" outline @click="goToEdit">
                Editar
              </q-btn>
            </div>
          </div>

          <div class="w-full md:p-5 px-4 pt-2 pb-7" style="border-top: 1px solid lightgray;">
            <div class="row q-col-gutter-md pb-5">
              <div class="col-12 col-md-7">
                <div class="space-y-4">
                  <div class="flex justify-between items-center pb-2"
                    style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                    <span class="text-gray-600 font-medium">Lectura anterior</span>
                    <span class="text-gray-900 font-semibold">{{ reading.previous_reading }}</span>
                  </div>

                  <div class="flex justify-between items-center pb-2"
                    style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                    <span class="text-gray-600 font-medium">Lectura actual</span>
                    <span class="text-gray-900 font-semibold">{{ reading.current_reading }}</span>
                  </div>

                  <div class="flex justify-between items-center pb-2"
                    style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                    <span class="text-gray-600 font-medium">Consumo (m³)</span>
                    <span class="text-gray-900 font-semibold">{{ consumptionM3 ?? '-' }}</span>
                  </div>

                  <div class="flex justify-between items-center pb-2"
                    style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                    <span class="text-gray-600 font-medium">Precio por m³ (S/.)</span>
                    <span class="text-gray-900 font-semibold">{{ reading.m3_price ?? '-' }}</span>
                  </div>

                  <div class="flex justify-between items-center pb-2"
                    style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
                    <span class="text-gray-600 font-medium">Subtotal agua (S/.)</span>
                    <span class="text-gray-900 font-semibold">
                      {{ subtotalSoles !== null ? subtotalSoles : '-' }}
                    </span>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-5">
                <div class="text-gray-600 font-medium mb-2">Foto del medidor</div>
                <div
                  class="rounded-lg overflow-hidden border border-gray-200 bg-grey-2 flex flex-col items-center justify-center"
                  style="min-height: 200px;">
                  <template v-if="photoUrl && !imageBroken">
                    <q-img :src="photoUrl" fit="contain" style="max-height: 360px;" spinner-color="primary"
                      @error="imageBroken = true" />
                    <div class="w-full p-2 text-center bg-white border-t border-gray-100">
                      <a :href="photoUrl" target="_blank" rel="noopener" class="text-primary text-caption">
                        Abrir imagen en nueva pestaña
                      </a>
                    </div>
                  </template>
                  <template v-else>
                    <q-icon name="eva-image-outline" size="3rem" color="grey-5" class="q-mt-md" />
                    <p class="text-grey-6 text-center q-px-md q-pb-md q-mb-none">
                      {{ photoUrl ? 'No se pudo cargar la imagen.' : 'No hay foto registrada.' }}
                    </p>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Medición no encontrada</h2>
        <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
      </div>
    </div>
  </div>
</template>
