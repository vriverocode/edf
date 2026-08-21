<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import { useWaterReadingsStore } from '@/services/store/waterReadings.store'
import { usePaginationState } from '@/composables/usePaginationState'

const router = useRouter()
const waterReadingsStore = useWaterReadingsStore()

const loading = ref(false)
const ready = ref(false)

const lastPage = ref(1)

const now = new Date()
const selectedMonth = ref(now.getMonth())
const selectedYear = ref(now.getFullYear())
const availableYears = ref([])

const { page, restoreFromQuery, syncToUrl, onPageChange } = usePaginationState({
  filters: [
    { key: 'month', ref: selectedMonth, parse: Number },
    { key: 'year', ref: selectedYear, parse: Number }
  ]
})

const readings = ref([])

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

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const selectedMonthObj = computed(() => monthOptions.find(m => m.value === selectedMonth.value) || monthOptions[0])

const consumption = (r) => {
  const prev = Number(r.previous_reading)
  const cur = Number(r.current_reading)
  if (!Number.isFinite(prev) || !Number.isFinite(cur)) return null
  return Number((cur - prev).toFixed(2))
}

const subtotal = (r) => {
  const cons = consumption(r)
  const price = Number(r.m3_price)
  if (cons === null || !Number.isFinite(price)) return null
  return Number((cons * price).toFixed(2))
}

const fetchReadings = async () => {
  loading.value = true
  ready.value = false
  try {
    const response = await waterReadingsStore.getWaterReadings({
      page: page.value,
      per_page: 12,
      month: selectedMonth.value,
      year: selectedYear.value
    })
    if (response?.code !== 200) throw response

    const payload = response.data || {}
    const pagination = payload.pagination || {}

    readings.value = pagination.data || []
    lastPage.value = pagination.last_page || 1
    availableYears.value = payload.available_years || []
    totalReadings.value = payload.total_readings || pagination.total || readings.value.length
    totalDepartments.value = payload.total_departments || 0

    ready.value = true
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo cargar la lista de mediciones')
  } finally {
    loading.value = false
  }
}

const goTo = (url) => router.push(url)

const totalReadings = ref(0)
const totalDepartments = ref(0)

const progressText = computed(() => {
  if (totalDepartments.value === 0) return ''
  return `${totalReadings.value} de ${totalDepartments.value} departamentos registrados`
})

const onChangeMonth = () => {
  page.value = 1
  syncToUrl()
  fetchReadings()
}

const onChangeYear = () => {
  page.value = 1
  syncToUrl()
  fetchReadings()
}

const startSequential = () => {
  goTo('/admin/water_readings/form/add?sequential=1&month=' + selectedMonth.value + '&year=' + selectedYear.value)
}

onMounted(() => {
  restoreFromQuery()
  fetchReadings()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: hidden;">
      <div class="px-2 pb-3 pt-0 md:px-28 h-full">
        <div class="flex justify-between md:pr-5 pr-1 items-center md:px-8" style="height: 8%;">
          <div class="row items-center w-full">
            <div class="col-7 col-md-4 pr-2">
              <q-select dense borderless class="form__inputsR" v-model="selectedMonth" :options="monthOptions"
                option-label="name" option-value="value" emit-value map-options @update:model-value="onChangeMonth" />
            </div>
            <div class="col-5 col-md-2">
              <q-input dense borderless class="form__inputsR" type="number" v-model.number="selectedYear"
                @update:model-value="onChangeYear" />
            </div>
          </div>
        </div>

        <div v-if="loading && !ready" class="flex justify-center items-center py-20" style="height: 93%;">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

        <div v-else class="pt-4 md:px-5 pb-8" style="height: 82%; overflow: auto;">
          <template v-if="readings.length > 0">
            <div class="mb-1">
              <div v-for="r in readings" :key="r.id" class="bg-white bills__container mb-5" style="position: relative;">
                <div class="pb-3 pt-2">
                  <div class="flex justify-between items-center pb-1 px-4" style="border-bottom: 1px solid lightgrey">
                    <div class="text-lg font-bold text-gray-900 mb-0" style="text-decoration: underline;"
                      @click="goTo('/admin/water_readings/view/' + r.id)">
                      Dpt: {{ r.departament?.number ?? r.departament_id }} • {{ r.month_label }} {{ r.year }}
                    </div>
                    <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                      <div v-html="iconsApp.optionsBook" />
                      <q-menu>
                        <q-list style="min-width: 150px">
                          <q-item clickable v-close-popup @click="goTo('/admin/water_readings/view/' + r.id)">
                            <q-item-section>Ver detalle</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click="goTo('/admin/water_readings/edit/' + r.id)">
                            <q-item-section>Modificar</q-item-section>
                          </q-item>
                        </q-list>
                      </q-menu>
                    </div>
                  </div>

                  <div class="row px-4 pt-1">
                    <div class="col-6 text-sm text-gray-700 mt-1">
                      Lectura anterior:
                      <div class="font-medium">{{ r.previous_reading }}</div>
                    </div>
                    <div class="col-6 text-sm text-gray-700 mt-1 flex column items-end">
                      Lectura actual:
                      <div class="font-medium">{{ r.current_reading }}</div>
                    </div>
                    <div class="col-6 text-sm text-gray-700 mt-2">
                      Consumo (m³):
                      <div class="font-medium">{{ consumption(r) ?? '-' }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-center mt-4">
              <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
                @update:model-value="onPageChange(fetchReadings)" />
            </div>
          </template>

          <template v-else>
            <div class="flex flex-col items-center justify-center py-20">
              <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay mediciones</h3>
              <p class="text-gray-600 text-center mb-6">
                No se registraron mediciones para {{ selectedMonthObj.name }} {{ selectedYear }}.
              </p>
            </div>
          </template>
        </div>

        <div v-if="!loading && ready" style="height: 10%;">
          <div class="row items-center q-mt-sm q-mb-xs px-2 md:px-0">
            <div v-if="progressText" class="col-12 col-md-3 text-caption text-grey-7 q-mb-xs q-mb-md-0">
              <q-linear-progress :value="totalDepartments > 0 ? totalReadings / totalDepartments : 0" color="primary"
                size="8px" class="q-mb-xs" style="border-radius: 4px;" />
              {{ progressText }}
            </div>
            <div class="col-12 row">
              <div class="col-6 col-md-6 px-1 md:px-2 flex flex-center">
                <q-btn no-caps color="grey-6" unelevated class="createButton mt-2 md:mt-0"  style="border-radius: 0.5rem"
                  @click="startSequential">
                   <div class="  pl-1 py-1 md:py-0">
                     Registro secuencial
                   </div>
                </q-btn>
              </div>
              <div class="col-6 col-md-6 px-1 md:px-2 flex flex-center">
                <q-btn no-caps color="primary" unelevated class="createButton mt-2 md:mt-0"  style="border-radius: 0.5rem;"
                  @click="goTo('/admin/water_readings/form/add')">
                  <div class="flex items-center">
                    <div class=" text-bold pl-1 py-1 md:py-0">
                      Registrar medición
                    </div>
                  </div>
                </q-btn>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.bills__container {
  border: 2px solid lightgray;
  border-radius: 1rem;
}

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
</style>
