<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import filterModal from '@/components/monthlyBills/filterModal.vue'
import { useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import { usePaginationState } from '@/composables/usePaginationState'


const loading = ref(false)
const ready = ref(false)
const monthlyBillsStore = useMonthlyBillsStore()
const router = useRouter()
const dialog = ref('')

const lastPage = ref(1)
const selectedBill = ref({})
const selectedYears = ref([])
const availableYears = ref([])
const generatingId = ref(null)

const { page, restoreFromQuery, syncToUrl, onPageChange } = usePaginationState({
  filters: [
    { key: 'years', ref: selectedYears, parse: (v) => (Array.isArray(v) ? v.map(Number) : v ? [Number(v)] : []) }
  ]
})

const bills = ref([])

const monthLabel = (month) => {
  const map = {
    1: 'Enero',
    2: 'Febrero',
    3: 'Marzo',
    4: 'Abril',
    5: 'Mayo',
    6: 'Junio',
    7: 'Julio',
    8: 'Agosto',
    9: 'Septiembre',
    10: 'Octubre',
    11: 'Noviembre',
    12: 'Diciembre'
  }
  return map[month] || `Mes ${month}`
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const hasActiveFilter = computed(() => (selectedYears.value || []).length > 0)

const fetchMonthlyBills = async () => {
  loading.value = true
  ready.value = false
  try {
    const response = await monthlyBillsStore.getMonthlyBills({
      page: page.value,
      per_page: 12,
      years: selectedYears.value
    })
    if (response?.code !== 200) throw response

    const payload = response.data || {}
    const pagination = payload.pagination || {}

    bills.value = pagination.data || []
    lastPage.value = pagination.last_page || 1
    availableYears.value = payload.available_years || []
    
    ready.value = true
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo cargar la lista de presupuestos'
    showNotify('negative', apiError)
  } finally {
    loading.value = false
  }
}
const showDialog = (e) => {
  const dialogData = getDialogData(e)
  selectBill(dialogData.bills)

  console.error(selectedBill.value)
  // setTimeout(() => {
  //   dialog.value = dialogData.dialog;
  // }, 500);
}
const selectBill = (id) => {
  selectedBill.value = bills.value.find(bill => bill.id == id)
}
const getDialogData = (e) => {
  return e.target.closest('.q-item').dataset
}
const updateListWithFilter = (newFilter) => {
  selectedYears.value = newFilter?.years || []
  page.value = 1
  syncToUrl()
  fetchMonthlyBills()
}

const clearFilters = () => {
  selectedYears.value = []
  page.value = 1
  syncToUrl()
  fetchMonthlyBills()
}
const confirmDialog = computed({
  get: () => dialog.value === 'confirm-quota',
  set: (val) => {
    if (!val) dialog.value = ''
  }
})

const askEmitQuotas = (bill) => {
  selectedBill.value = bill
  dialog.value = 'confirm-quota'
}

const emitQuotas = async () => {
  const bill = selectedBill.value
  if (!bill?.id || generatingId.value !== null) return

  generatingId.value = bill.id
  dialog.value = ''
  try {
    const response = await monthlyBillsStore.generateQuotas(bill.id)
    if (response?.code !== 200) throw response

    const updated = response.data?.monthly_bill
    if (updated) Object.assign(bill, updated)

    const generated = response.data?.generated ?? 0
    showNotify('positive', `Se emitieron ${generated} cuota${generated === 1 ? '' : 's'} de ${monthLabel(bill.month)} ${bill.year}`)
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudieron emitir las cuotas'
    showNotify('negative', apiError)
  } finally {
    generatingId.value = null
  }
}
const goTo = (url) => {
  router.push(url)
}
showDialog

onMounted(() => {
  restoreFromQuery()
  fetchMonthlyBills()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: hidden;">
      <div class="px-2 pb-6 pt-0 md:px-28 h-full" >
        <div class="flex justify-end md:pr-5 pr-1 items-center" style="height: 10%;">
          <q-btn
            outline
            color="primary"
            icon="eva-funnel-outline"
            @click="dialog = 'filter'"
          />
          <q-btn
            v-if="hasActiveFilter"
            class="ml-2"
            outline
            color="grey-7"
            icon="eva-close-outline"
            @click="clearFilters"
          >
            <q-tooltip class="bg-primary text-white text-body2" :offset="[10, 10]">
              Limpiar filtros
            </q-tooltip>
          </q-btn>
        </div>
        <div v-if="!loading && ready" style="height: 10%;">
          <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-full">
            <q-btn color="primary" unelevated class="w-full mt-5 md:mx-5 createButton " style="border-radius: 0.5rem;"
              @click="goTo('/admin/monthly_bills/form/add')">
              <div class="flex items-center py-1">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">
                  Crear nuevo
                </div>
              </div>
            </q-btn>
          </div>
  
        </div>

        <div v-if="loading && !ready" class="flex justify-center items-center py-20" style="height: 90%;">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

        <div v-else class="pt-3 md:px-5 pb-8" style="height: 80%; overflow:auto">
          <template v-if="bills.length > 0">
            <div class="">
              <div
                v-for="bill in bills"
                :key="bill.id"
                class="bg-white bills__container mb-5"
                style="position: relative;"
              >
                <div class=" pb-4 pt-2 ">
                  <div class="flex justify-between items-center pb-1 px-4" style="border-bottom: 1px solid lightgrey" >
                    <div class="f">
                      <div class="text-lg font-bold text-gray-900 mb-0">
                        {{ monthLabel(bill.month) }} - {{ bill.year }}
                      </div>
                      
                    </div>
                    <div class="flex items-center">
                      <q-btn
                        v-if="bill.generated_at"
                        flat
                        rounded
                        color="positive"
                        size="sm"
                        disable
                      >
                        <div class="flex items-center">
                          <q-icon name="eva-checkmark-circle-outline" class="q-pr-xs" />
                          Emitidas
                        </div>
                      </q-btn>
                      <q-btn
                        v-else
                        flat
                        rounded
                        color="primary"
                        size="sm"
                        :loading="generatingId === bill.id"
                        :disable="generatingId !== null"
                        @click="askEmitQuotas(bill)"
                      >
                        <div class="flex items-center">
                          <q-icon name="eva-send-outline" class="q-pr-xs" />
                          Emitir cuotas
                        </div>
                      </q-btn>
                      <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer">
                        <div v-html="iconsApp.optionsBook" />
                        <q-menu>
                          <q-list style="min-width: 150px">
                            <q-item clickable v-close-popup @click="goTo('/admin/monthly_bills/view/' + bill.id)">
                              <q-item-section>Ver detalles</q-item-section>
                            </q-item>
                            <q-item clickable v-close-popup @click="goTo('/admin/monthly_bills/edit/' + bill.id)" data-dialog="edit"
                              :data-bills="bill.id" >
                              <q-item-section>Modificar</q-item-section>
                            </q-item>
                          </q-list>
                        </q-menu>
                      </div>
                    </div>
                  </div>

                  <div class="row px-4 pt-1">
                    <div class="col-8 col-md-6">
                      <div class="text-sm text-gray-700 mt-1">
                        Presupuesto mantenimiento: <div class="font-medium">S/ {{ bill.total_maintenance_budget }}</div>
                      </div>
                    </div>
                    <div class="col-4 col-md-6 text-sm text-gray-700 mt-1  flex column items-end">
                      Agua (S/. total): <div class="font-medium">S/ {{ bill.total_water_bill_amount ?? '-' }}</div>
                    </div>
                    <div class="col-6 col-md-6 text-sm text-gray-700 mt-2 ">
                      Consumo (m3): <div class="font-medium">{{ bill.total_water_consumption_m3 ?? '-' }}</div>
                    </div>
                    <div class="col-6 text-sm text-gray-700 mt-2 flex column items-end">
                      Costo por m3 (S/.): <div class="font-medium">S/ {{ bill.water_price_per_m3 }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-center mt-4">
              <q-pagination
                v-model="page"
                color="primary"
                :max="lastPage"
                :max-pages="4"
                :boundary-numbers="false"
                @update:model-value="onPageChange(fetchMonthlyBills)"
              />
            </div>
          </template>

          <template v-else>
            <div class="flex flex-col items-center justify-center py-20">
              <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
              </div>
              <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay presupuestos mensuales</h3>
              <p class="text-gray-600 text-center mb-6">Aún no se han registrado presupuestos.</p>
            </div>
          </template>

        </div>
        
      </div>
    </div>

    <filterModal
      :dialog="dialog === 'filter'"
      :years="availableYears"
      :selectedYears="selectedYears"
      @closeModal="dialog = ''"
      @updateList="updateListWithFilter"
    />

    <q-dialog v-model="confirmDialog" persistent>
      <q-card style="min-width: 320px">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Emitir cuotas</div>
          <q-space />
          <q-btn icon="eva-close-outline" flat round dense v-close-popup />
        </q-card-section>
        <q-card-section>
          ¿Seguro que deseas emitir las cuotas de {{ monthLabel(selectedBill?.month) }} {{ selectedBill?.year }}?
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-7" v-close-popup />
          <q-btn unelevated label="Emitir" color="primary" :loading="generatingId !== null" @click="emitQuotas" />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>
<style lang="scss">
.bills__container{
  border: 2px solid lightgray;
  border-radius: 1rem;
}
</style>
