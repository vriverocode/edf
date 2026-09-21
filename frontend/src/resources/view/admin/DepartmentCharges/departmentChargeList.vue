<script setup>
import { ref, onMounted } from 'vue'
import { useDepartmentChargeStore } from '@/services/store/departmentCharge.store'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'

const chargeStore = useDepartmentChargeStore()
const router = useRouter()

const charges = ref([])
const loading = ref(true)
const pagination = ref({ page: 1, lastPage: 1, perPage: 20 })
const filters = ref({ search: '', status: null })
const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Activo', value: 1 },
  { label: 'Pagado', value: 2 },
  { label: 'Cancelado', value: 3 },
]

const fetchCharges = (page = 1) => {
  loading.value = true
  const params = { page, per_page: pagination.value.perPage }
  if (filters.value.search) params.search = filters.value.search
  if (filters.value.status !== null) params.status = filters.value.status
  chargeStore.getDepartmentCharges(params)
    .then((res) => {
      const pag = res.data.pagination || {}
      charges.value = pag.data || []
      pagination.value.lastPage = pag.last_page || 1
      pagination.value.page = pag.current_page || 1
    })
    .catch(() => {})
    .finally(() => { loading.value = false })
}

const applyFilters = () => { pagination.value.page = 1; fetchCharges(1) }

const goToCreate = () => router.push('/admin/department-charges/create')
const goToEdit = (id) => router.push(`/admin/department-charges/${id}/edit`)

const confirmDelete = (charge) => {
  Notify.create({
    title: 'Confirmar eliminación',
    message: `¿Eliminar el cargo "${charge.description}"?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    chargeStore.deleteDepartmentCharge(charge.id)
      .then(() => { Notify.create({ color: 'positive', message: 'Cargo eliminado' }); fetchCharges(pagination.value.page) })
      .catch((err) => Notify.create({ color: 'negative', message: err?.error || 'Error al eliminar' }))
  })
}

onMounted(() => fetchCharges())
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="reserve-list-footer px-4 flex justify-center items-center md:w-full md:px-12" style="height: 10%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem; width: 100%;" @click="goToCreate">
        <div class="flex items-center py-2">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">Registrar cargo extra</div>
        </div>
      </q-btn>
    </div>
    <div style="height: 90%; overflow: auto;">
      <div class="px-4 pt-2 md:px-36">
        <div class="w-full">
          <q-select dense outlined v-model="filters.status" label="Estado" color="primary"
            :options="statusOptions" option-label="label" option-value="value"
            emit-value map-options clearable @clear="applyFilters" @update:model-value="applyFilters" />
        </div>
        <q-input class="mt-2" dense outlined v-model="filters.search" placeholder="Buscar por departamento o descripción..."
          @keyup.enter="applyFilters" clearable @clear="applyFilters" color="teal">
          <template v-slot:prepend><q-icon name="eva-search-outline" /></template>
        </q-input>

        <div v-if="loading" class="flex justify-center items-center py-20">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

        <div v-else-if="charges.length" class="mt-4 pb-5">
          <div v-for="charge in charges" :key="charge.id"
            class="bg-white rounded-xl border border-gray-200 p-4 mb-3 cursor-pointer hover:shadow-md transition-shadow"
            @click="goToEdit(charge.id)">
            <div class="flex justify-between items-start">
              <div class="col">
                <div class="text-body1 font-bold text-gray-900">{{ charge.description }}</div>
                <div class="text-caption text-grey-6 mt-1">
                  {{ charge.departament?.number }} — Inicio: {{ charge.start_month }}/{{ charge.start_year }}
                </div>
                <div v-if="charge.expense" class="text-caption text-orange-7 mt-1">
                  Gasto vinculado: {{ charge.expense.description }} (S/. {{ charge.expense.amount }})
                </div>
              </div>
              <div class="col-auto text-right">
                <q-badge :color="charge.status_color" :label="charge.status_label" />
              </div>
            </div>
            <div class="flex justify-between items-center mt-3">
              <div class="text-body2 text-grey-7">
                Total: <span class="font-bold">S/. {{ charge.total_amount?.toFixed(2) }}</span>
              </div>
              <div class="text-body2 text-grey-7">
                Cuotas: <span class="font-bold">{{ charge.paid_installments }}/{{ charge.installments }}</span>
              </div>
              <div class="text-body2 text-grey-7">
                Mensual: <span class="font-bold text-primary">S/. {{ charge.monthly_amount?.toFixed(2) }}</span>
              </div>
              <q-btn flat round dense icon="eva-trash-2-outline" color="negative" size="sm" @click.stop="confirmDelete(charge)" />
            </div>
          </div>

          <div v-if="pagination.lastPage > 1" class="flex justify-center mt-4">
            <q-pagination v-model="pagination.page" :max="pagination.lastPage" :max-pages="6"
              boundary-numbers direction-links @update:model-value="(val) => fetchCharges(val)" />
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-alert-circle-outline" class="text-blue-500" size="2.5rem" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay cargos extras</h3>
          <p class="text-gray-600 text-center">Aún no se han registrado cargos extras a departamentos.</p>
        </div>
      </div>
    </div>
  </div>
</template>
