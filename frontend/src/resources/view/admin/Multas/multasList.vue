<script setup>
import { ref, onMounted } from 'vue'
import { useMultaStore } from '@/services/store/multa.store'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import moment from 'moment'

const multaStore = useMultaStore()
const router = useRouter()

const multas = ref([])
const loading = ref(true)
const pagination = ref({ page: 1, lastPage: 1, perPage: 20, total: 0 })
const filters = ref({ search: '', status: null })

const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Pendiente', value: 1 },
  { label: 'Pagado', value: 2 },
  { label: 'Anulado', value: 3 },
]

const fetchMultas = (page = 1) => {
  loading.value = true
  const params = { page, per_page: pagination.value.perPage }
  if (filters.value.search) params.search = filters.value.search
  if (filters.value.status !== null) params.status = filters.value.status

  multaStore.getMultas(params)
    .then((res) => {
      const pag = res.data || {}
      multas.value = pag.data || []
      pagination.value.lastPage = pag.last_page || 1
      pagination.value.page = pag.current_page || 1
      pagination.value.total = pag.total || 0
    })
    .catch((err) => {
      Notify.create({ color: 'negative', message: err || 'Error al cargar multas' })
    })
    .finally(() => {
      loading.value = false
    })
}

const applyFilters = () => {
  pagination.value.page = 1
  fetchMultas(1)
}

const goToCreate = () => router.push('/admin/multas/create')
const goToEdit = (id) => router.push(`/admin/multas/${id}/edit`)

const getStatusBadge = (status) => {
  switch (Number(status)) {
    case 1:
      return { label: 'Pendiente', color: 'warning' }
    case 2:
      return { label: 'Pagado', color: 'positive' }
    case 3:
      return { label: 'Anulado', color: 'negative' }
    default:
      return { label: '—', color: 'grey' }
  }
}

const confirmDelete = (multa) => {
  Notify.create({
    title: 'Confirmar eliminación',
    message: `¿Deseas eliminar la multa del departamento ${multa.departament?.number || ''}?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    multaStore.deleteMulta(multa.id)
      .then(() => {
        Notify.create({ color: 'positive', message: 'Multa eliminada con éxito' })
        fetchMultas(pagination.value.page)
      })
      .catch((err) => {
        Notify.create({ color: 'negative', message: err || 'Error al eliminar la multa' })
      })
  })
}

onMounted(() => {
  fetchMultas()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <!-- Top Action Bar -->
    <div class="reserve-list-footer px-4 flex justify-center items-center md:w-full md:px-12" style="height: 10%;">
      <div class="row w-full flex justify-between items-center">
        <div class="text-h6 text-bold text-gray-800">
          Multas y Sanciones
        </div>
        <q-btn
          color="primary"
          unelevated
          no-caps
          style="border-radius: 0.5rem;"
          @click="goToCreate"
        >
          <q-icon name="eva-plus-outline" size="1.2rem" class="mr-1" />
          Nueva Multa
        </q-btn>
      </div>
    </div>

    <!-- Main Content Area -->
    <div style="height: 90%; overflow: auto;" class="px-4 py-4 md:px-12">
      <!-- Filtros -->
      <div class="row q-col-gutter-sm items-center mb-4">
        <div class="col-12 col-md-5">
          <q-input
            v-model="filters.search"
            dense
            outlined
            placeholder="Buscar por departamento o descripción..."
            clearable
            @keyup.enter="applyFilters"
            @clear="applyFilters"
          >
            <template #prepend>
              <q-icon name="eva-search-outline" />
            </template>
          </q-input>
        </div>

        <div class="col-12 col-md-4">
          <q-select
            v-model="filters.status"
            :options="statusOptions"
            dense
            outlined
            emit-value
            map-options
            label="Estado"
            @update:model-value="applyFilters"
          />
        </div>

        <div class="col-12 col-md-3 flex justify-end">
          <q-btn
            color="primary"
            outline
            no-caps
            class="full-width"
            label="Filtrar"
            @click="applyFilters"
          />
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
      </div>

      <!-- Empty State -->
      <div v-else-if="!multas.length" class="flex flex-col items-center justify-center py-20 text-center">
        <div class="w-16 h-16 rounded-full bg-amber-50 flex items-center justify-center mb-4">
          <q-icon name="eva-alert-triangle-outline" size="2.5rem" color="warning" />
        </div>
        <div class="text-h6 text-gray-700">No se encontraron multas</div>
        <div class="text-caption text-gray-500 mt-1">Puedes registrar una nueva multa usando el botón superior.</div>
      </div>

      <!-- List Cards -->
      <div v-else class="space-y-3 pb-8">
        <div
          v-for="m in multas"
          :key="m.id"
          class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 relative hover:shadow-md transition-shadow"
        >
          <div class="row justify-between items-start">
            <!-- Left Info -->
            <div class="col-12 col-md-8">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-subtitle1 text-bold text-primary">
                  Dpto. {{ m.departament?.number || '—' }}
                </span>
                <span class="text-caption text-gray-500" v-if="m.departament?.owner?.name">
                  ({{ m.departament.owner.name }})
                </span>
                <q-chip
                  :color="getStatusBadge(m.status).color"
                  text-color="white"
                  dense
                  size="0.75rem"
                  class="text-bold"
                >
                  {{ getStatusBadge(m.status).label }}
                </q-chip>
                <q-chip v-if="m.rule?.code" color="blue-1" text-color="blue-9" dense size="0.75rem">
                  Norma: {{ m.rule.code }}
                </q-chip>
              </div>

              <div class="text-body2 text-gray-800 font-medium mb-1">
                {{ m.description }}
              </div>

              <div class="row q-gutter-x-md text-caption text-gray-600 mt-2">
                <div>
                  <span class="text-gray-400">Fecha del incidente:</span>
                  {{ m.incident_date ? moment(m.incident_date).format('DD/MM/YYYY') : '—' }}
                </div>
                <div>
                  <span class="text-gray-400">Aplica desde:</span>
                  {{ m.start_month ? `${m.start_month}/${m.start_year}` : 'Inmediato' }}
                </div>
                <div>
                  <span class="text-gray-400">Cuotas:</span>
                  {{ m.paid_installments || 0 }} de {{ m.installments || 1 }} pagadas
                </div>
              </div>
            </div>

            <!-- Right Amounts & Actions -->
            <div class="col-12 col-md-4 flex flex-col md:items-end justify-between mt-3 md:mt-0">
              <div class="text-right">
                <div class="text-h6 text-bold text-gray-900">
                  S/. {{ Number(m.amount || 0).toFixed(2) }}
                </div>
                <div class="text-caption text-gray-500" v-if="m.installments > 1">
                  {{ m.installments }} cuotas de S/. {{ Number(m.monthly_amount || 0).toFixed(2) }} / mes
                </div>
              </div>

              <div class="flex q-gutter-xs mt-3">
                <q-btn
                  flat
                  round
                  dense
                  color="primary"
                  icon="eva-edit-outline"
                  @click="goToEdit(m.id)"
                >
                  <q-tooltip>Editar multa</q-tooltip>
                </q-btn>
                <q-btn
                  flat
                  round
                  dense
                  color="negative"
                  icon="eva-trash-2-outline"
                  @click="confirmDelete(m)"
                >
                  <q-tooltip>Eliminar multa</q-tooltip>
                </q-btn>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.lastPage > 1" class="flex justify-center mt-6">
          <q-pagination
            v-model="pagination.page"
            :max="pagination.lastPage"
            direction-links
            boundary-links
            color="primary"
            @update:model-value="fetchMultas"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.reserve-list-footer {
  border-bottom: 1px solid rgba(211, 211, 211, 0.4);
}
</style>

