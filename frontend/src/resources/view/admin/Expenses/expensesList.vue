<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import { useExpenseStore } from '@/services/store/expense.store'
import { useProviderStore } from '@/services/store/provider.store'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'
import moment from 'moment'
import iconsApp from '@/assets/icons/index'
import gastos from '@/assets/img/menu/gastos2.png'
import expenseFilterModal from '@/components/expenses/expenseFilterModal.vue'
import { usePaginationState } from '@/composables/usePaginationState'

const router = useRouter()
const expenseStore = useExpenseStore()
const providerStore = useProviderStore()
const serviceCategoryStore = useServiceCategoryStore()

const loading = ref(false)
const ready = ref(false)
const showFilterDialog = ref(false)
const lastPage = ref(1)
const expenses = ref([])

const providerOptions = ref([])
const categoryOptions = ref([])

const now = new Date()
const filter = ref({
  month: null,
  year: now.getFullYear(),
  status: null,
  provider_id: null,
  category_id: null,
  date_from: null,
  date_to: null
})

const { page, restoreFromQuery, syncToUrl, onPageChange } = usePaginationState({
  filters: [
    { key: 'month', get: () => filter.value.month, set: (v) => { filter.value.month = v === '' ? null : Number(v) } },
    { key: 'year', get: () => filter.value.year, set: (v) => { filter.value.year = Number(v) } },
    { key: 'status', get: () => filter.value.status, set: (v) => { filter.value.status = v === '' ? null : Number(v) } },
    { key: 'provider_id', get: () => filter.value.provider_id, set: (v) => { filter.value.provider_id = v === '' ? null : Number(v) } },
    { key: 'category_id', get: () => filter.value.category_id, set: (v) => { filter.value.category_id = v === '' ? null : Number(v) } },
    { key: 'date_from', get: () => filter.value.date_from, set: (v) => { filter.value.date_from = v || null } },
    { key: 'date_to', get: () => filter.value.date_to, set: (v) => { filter.value.date_to = v || null } }
  ]
})

const monthOptions = [
  { value: null, name: 'Todos' },
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

const statusOptions = [
  { value: null, name: 'Todos' },
  { value: 1, name: 'Pendiente' },
  { value: 2, name: 'Aprobado para pago' },
  { value: 3, name: 'Pagado' }
]

const hasActiveFilter = computed(() => {
  return !!(filter.value.month || filter.value.status || filter.value.year !== now.getFullYear()
    || filter.value.provider_id || filter.value.category_id
    || filter.value.date_from || filter.value.date_to)
})

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const formatDate = (value) => {
  if (!value) return '—'
  const parsed = moment(value, 'YYYY-MM-DD', true)
  if (!parsed.isValid()) return value
  return parsed.format('DD/MM/YYYY')
}

const formatMoney = (value) => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '—'
  return `S/. ${n.toFixed(2)}`
}

const monthlyBillLabel = (bill) => {
  if (!bill) return '—'
  const month = monthOptions.find((m) => m.value === bill.month)?.name || bill.month
  return `${month} ${bill.year}`
}

const statusClass = (status) => {
  const map = {
    1: 'bg-orange-500',
    2: 'bg-blue-500',
    3: 'bg-green-600'
  }
  return map[status] || 'bg-grey-6'
}

const fetchExpenses = async () => {
  loading.value = true
  ready.value = false
  try {
    const response = await expenseStore.getExpenses({
      page: page.value,
      per_page: 12,
      month: filter.value.month || undefined,
      year: filter.value.year || undefined,
      status: filter.value.status || undefined,
      provider_id: filter.value.provider_id || undefined,
      category_id: filter.value.category_id || undefined,
      date_from: filter.value.date_from || undefined,
      date_to: filter.value.date_to || undefined
    })
    if (response?.code !== 200) throw response

    const pagination = response.data?.pagination || {}
    expenses.value = pagination.data || []
    lastPage.value = pagination.last_page || 1
    ready.value = true
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo cargar la lista de gastos'
    showNotify('negative', apiError)
  } finally {
    loading.value = false
  }
}

const openFilter = () => {
  showFilterDialog.value = true
}

const applyFilter = (newFilter) => {
  filter.value = { ...newFilter }
  page.value = 1
  showFilterDialog.value = false
  syncToUrl()
  fetchExpenses()
}

const clearFilters = () => {
  filter.value = { month: null, year: now.getFullYear(), status: null, provider_id: null, category_id: null, date_from: null, date_to: null }
  page.value = 1
  syncToUrl()
  fetchExpenses()
}

const goTo = (url) => router.push(url)

const openAttachment = (url) => {
  if (url) window.open(url, '_blank')
}

const loadFilterOptions = async () => {
  try {
    const [provRes, catRes] = await Promise.all([
      providerStore.getProviders({ per_page: 100 }),
      serviceCategoryStore.getServiceCategories()
    ])
    if (provRes?.code === 200) {
      const list = provRes.data?.pagination?.data || provRes.data || []
      providerOptions.value = list.map(p => ({ label: p.name, value: p.id }))
    }
    if (catRes?.code === 200 && Array.isArray(catRes.data)) {
      categoryOptions.value = catRes.data.map(c => ({ label: c.name, value: c.id }))
    }
  } catch {
    // silencio
  }
}

onMounted(() => {
  restoreFromQuery()
  loadFilterOptions()
  fetchExpenses()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: hidden;">
      <div class="px-2 pb-6 pt-0 md:px-28 h-full">
        <div class="row md:pr-5 pr-1 justify-end " style="height: 17%;">
          <div class="col-12 col-md-3 md:px-4  flex justify-end items-center ">
            <q-btn outline color="primary" icon="eva-funnel-outline" @click="openFilter">
              <q-badge v-if="hasActiveFilter" floating color="yellow" rounded style="width: 10px; height: 10px; min-width: 10px;" />
            </q-btn>
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
          <div class="col-12 col-md-4 flex flex-center">
            <q-btn
              color="primary"
              unelevated
              class="w-full  createButton"
              style="border-radius: 0.5rem;"
              @click="goTo('/admin/expenses/form/add')"
            >
              <div class="flex items-center py-1">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">Registrar gasto</div>
              </div>
            </q-btn>
          </div>
        </div>

        <div v-if="loading && !ready" class="flex justify-center items-center py-20" style="height: 93%;">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

        <div v-else class="pt-3 md:px-5 pb-8" style="height: 83%; overflow: auto">
          <template v-if="expenses.length > 0">
            <div
              v-for="expense in expenses"
              :key="expense.id"
              class="bg-white expenses__container mb-5"
            >
              <div class="pb-4 pt-2">
                <div class="flex justify-between items-center pb-1 px-4" style="border-bottom: 1px solid lightgrey">
                  <div @click="goTo('/admin/expenses/details/' + expense.id)" class="cursor-pointer">
                    <div class="text-lg font-bold text-gray-900 mb-0">
                      {{ expense.provider?.name || 'Proveedor' }}
                    </div>
                    <div v-if="expense.service_category?.name" class="text-xs text-primary font-medium">
                      {{ expense.service_category.name }}
                    </div>
                    <div class="text-sm text-gray-600">
                      {{ formatMoney(expense.amount) }}
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                      <q-btn v-if="expense.attachment_url" flat dense round color="primary" icon="eva-attach-outline" size="sm"
                        @click.stop="openAttachment(expense.attachment_url)">
                        <q-tooltip>Ver comprobante</q-tooltip>
                      </q-btn>
                      <span
                        :class="statusClass(expense.status)"
                        class="inline-block px-3 py-1 text-xs font-bold text-white rounded-full"
                      >
                        {{ expense.status_label }}
                      </span>
                      <div class="cursor-pointer">
                      <div v-html="iconsApp.optionsBook" />
                      <q-menu>
                        <q-list style="min-width: 150px">
                          <q-item clickable v-close-popup @click="goTo('/admin/expenses/details/' + expense.id)">
                            <q-item-section>Ver detalles</q-item-section>
                          </q-item>
                          <q-item clickable v-close-popup @click="goTo('/admin/expenses/edit/' + expense.id)">
                            <q-item-section>Modificar</q-item-section>
                          </q-item>
                        </q-list>
                      </q-menu>
                    </div>
                  </div>
                </div>
                <div class="row px-4 pt-2 cursor-pointer" @click="goTo('/admin/expenses/details/' + expense.id)" >
                  <div class="col-12 col-md-6 text-sm text-gray-700 mt-1">
                    Tipo: <span class="font-medium">{{ expense.expense_type_label }}</span>
                  </div>
                  <div v-if="expense.invoice_number" class="col-6 text-sm text-gray-700 md:mt-0 mt-2">
                    Factura N°: <span class="font-medium">{{ expense.invoice_number }}</span>
                  </div>
                  <div class="col-6 col-md-6 text-sm text-gray-700 mt-2  md:text-start text-end">
                    Presupuesto: <span class="font-medium">{{ monthlyBillLabel(expense.monthly_bill) }}</span>
                  </div>
                  <div class="col-6 text-sm text-gray-700 mt-2">
                    Emisión: <span class="font-medium">{{ formatDate(expense.issue_date) }}</span>
                  </div>
                  <div class="col-6 text-sm text-gray-700 mt-2 md:text-start text-end">
                    Vence: <span class="font-medium">{{ formatDate(expense.due_date) }}</span>
                  </div>
                  <div class="col-12 col-md-6 text-sm text-gray-700 mt-2 line-clamp-2">
                    <b>Descripción:</b>
                    {{ expense.description }}
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
                @update:model-value="onPageChange(fetchExpenses)"
              />
            </div>
          </template>

          <template v-else>
            <div class="py-20">
              <div class="w-full flex justify-center">
                <div class="w-24 h-24 bg-primary rounded-full flex items-center justify-center mb-6">
                  <img :src="gastos" class="md:w-auto h-3/5"/>
                </div>
              </div>
              <div class="flex flex-col items-center justify-center py-5">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay gastos registrados</h3>
                <p class="text-gray-600 text-center mb-6">Aún no se han registrado gastos para los filtros seleccionados.</p>
              </div>
            </div>
          </template>
        </div>

      </div>
    </div>

    <expenseFilterModal
      :dialog="showFilterDialog"
      :current-filters="filter"
      :month-options="monthOptions"
      :status-options="statusOptions"
      :provider-options="providerOptions"
      :category-options="categoryOptions"
      @closeModal="showFilterDialog = false"
      @applyFilter="applyFilter"
    />
  </div>
</template>

<style lang="scss" >
.expenses__container {
  border: 2px solid lightgray;
  border-radius: 1rem;
}

</style>
