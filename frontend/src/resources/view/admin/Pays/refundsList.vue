<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import moment from 'moment'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'

const router = useRouter()
const payStore = usePayStore()

const statusOptions = [
  { label: 'Todos', value: '' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Completada', value: 'completed' },
]

const monthOptions = [
  { label: 'Enero', value: 1 },
  { label: 'Febrero', value: 2 },
  { label: 'Marzo', value: 3 },
  { label: 'Abril', value: 4 },
  { label: 'Mayo', value: 5 },
  { label: 'Junio', value: 6 },
  { label: 'Julio', value: 7 },
  { label: 'Agosto', value: 8 },
  { label: 'Septiembre', value: 9 },
  { label: 'Octubre', value: 10 },
  { label: 'Noviembre', value: 11 },
  { label: 'Diciembre', value: 12 },
]

const currentYear = new Date().getFullYear()
const yearOptions = [currentYear, currentYear - 1, currentYear - 2].map((y) => ({
  label: String(y),
  value: y,
}))

const loading = ref(true)
const refunds = ref([])
const filterUser = ref('')
const filterDept = ref('')
const filterStatus = ref(statusOptions[0])
const filterMonth = ref(null)
const filterYear = ref(yearOptions[0])
const voucherDialog = ref(null)
const pagination = ref({ page: 1, lastPage: 1, perPage: 20, total: 0 })

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const columns = [
  {
    name: 'created_at',
    label: 'Fecha',
    field: 'created_at',
    format: (v) => (v ? moment(v).format('DD/MM/YYYY HH:mm') : '—'),
    align: 'center',
  },
  { name: 'booking_number', label: 'Reserva', field: 'booking_number', align: 'center' },
  { name: 'user_name', label: 'Usuario', field: 'user_name', align: 'left' },
  { name: 'departament_number', label: 'Depto', field: 'departament_number', align: 'center' },
  { name: 'comun_area', label: 'Área común', field: 'comun_area', align: 'center' },
  { name: 'status_label', label: 'Estado', field: 'status_label', align: 'center' },
  { name: 'kind_label', label: 'Tipo', field: 'kind_label', align: 'center' },
  {
    name: 'amount',
    label: 'Monto',
    field: 'amount',
    format: (v) => formatMoney(v),
    align: 'right',
  },
  { name: 'voucher', label: 'Voucher', field: 'vaucher', align: 'center' },
  { name: 'actions', label: '', field: '', align: 'center' },
]

const goToDetail = (row) => {
  if (row?.row_key) router.push(`/admin/pays/refunds/detail/${row.row_key}`)
}

const getRefunds = (page = 1) => {
  loading.value = true
  const params = { paginate: pagination.value.perPage, page }
  if (filterUser.value) params.user = filterUser.value
  if (filterDept.value) params.departament = filterDept.value
  if (filterStatus.value?.value) params.status = filterStatus.value.value
  if (filterMonth.value?.value) {
    params.month = filterMonth.value.value
    params.year = filterYear.value?.value
  }

  payStore
    .getRefunds(params)
    .then((data) => {
      refunds.value = data?.data || []
      pagination.value.page = data?.current_page || 1
      pagination.value.lastPage = data?.last_page || 1
      pagination.value.total = data?.total || 0
    })
    .catch(() => {})
    .finally(() => {
      loading.value = false
    })
}

const applyFilters = () => {
  pagination.value.page = 1
  getRefunds(1)
}

const clearFilters = () => {
  filterUser.value = ''
  filterDept.value = ''
  filterStatus.value = statusOptions[0]
  filterMonth.value = null
  filterYear.value = yearOptions[0]
  applyFilters()
}

const onPageChange = (page) => {
  pagination.value.page = page
  getRefunds(page)
}

const openVoucher = (r) => {
  if (!r.vaucher) return
  voucherDialog.value = r.vaucher
}

const kindColor = (kind) => (kind === 'warranty' ? 'blue-2' : 'orange-2')
const kindTextColor = (kind) => (kind === 'warranty' ? 'blue-9' : 'orange-9')

onMounted(() => {
  getRefunds(1)
})
</script>

<template>
  <div class="h-full" style="overflow: hidden">
    <div style="height: 100%; overflow: auto">
      <div class="px-4 pt-4 md:px-36">
        <div class="text-center text-black text-h5 text-bold mb-3">Devoluciones</div>

        <!-- Resumen -->


        <!-- Filtros -->
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-md-5">
            <q-input
              dense
              outlined
              v-model="filterUser"
              placeholder="Buscar por usuario (nombre o correo)"
              @keyup.enter="applyFilters"
              clearable
              @clear="applyFilters"
              color="teal"
            >
              <template v-slot:prepend>
                <q-icon name="eva-person-outline" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-5">
            <q-input
              dense
              outlined
              v-model="filterDept"
              placeholder="Buscar por departamento (número)"
              @keyup.enter="applyFilters"
              clearable
              @clear="applyFilters"
              color="teal"
            >
              <template v-slot:prepend>
                <q-icon name="eva-home-outline" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-2">
            <q-btn
              unelevated
              no-caps
              color="primary"
              icon="eva-search-outline"
              label="Buscar"
              class="text-bold w-full"
              style="border-radius: 0.2rem"
              @click="applyFilters"
            />
          </div>
        </div>

        <div class="row q-col-gutter-sm items-center q-mt-sm">
          <div class="col-12 col-md-4">
            <q-select
              dense
              outlined
              v-model="filterStatus"
              :options="statusOptions"
              label="Estado"
              color="teal"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="col-6 col-md-3">
            <q-select
              dense
              outlined
              v-model="filterMonth"
              :options="monthOptions"
              label="Mes"
              color="teal"
              clearable
              @clear="applyFilters"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="col-6 col-md-3">
            <q-select
              dense
              outlined
              v-model="filterYear"
              :options="yearOptions"
              label="Año"
              color="teal"
              @update:model-value="applyFilters"
            />
          </div>
          <div class="col-12 col-md-2">
            <q-btn
              flat
              dense
              no-caps
              color="grey-7"
              icon="eva-refresh-outline"
              label="Limpiar"
              class="w-full"
              @click="clearFilters"
            />
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Lista -->
      <div v-else class="px-4 py-4 md:px-28">
        <div v-if="refunds.length === 0" class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-undo-outline" class="text-blue-500" size="2.5rem" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay devoluciones</h3>
          <p class="text-gray-600 text-center">
            Ninguna devolución coincide con los filtros aplicados.
          </p>
        </div>

        <template v-else>
          <q-table
            flat
            bordered
            :rows="refunds"
            :columns="columns"
            row-key="row_key"
            :pagination="{ rowsPerPage: 0 }"
            hide-pagination
            class="cursor-pointer"
            @row-click="(evt, row) => goToDetail(row)"
          >
            <template #body-cell-status_label="props">
              <q-td :props="props" auto-width>
                <q-badge :color="props.row.status_color" :label="props.row.status_label" />
              </q-td>
            </template>
            <template #body-cell-kind_label="props">
              <q-td :props="props" auto-width>
                <q-badge
                  :color="kindColor(props.row.kind)"
                  :text-color="kindTextColor(props.row.kind)"
                  :label="props.row.kind_label"
                />
              </q-td>
            </template>
            <template #body-cell-voucher="props">
              <q-td :props="props" auto-width>
                <q-btn
                  v-if="props.row.vaucher"
                  flat
                  dense
                  color="primary"
                  icon="eva-file-outline"
                  label="Ver"
                  no-caps
                  size="sm"
                  @click.stop="openVoucher(props.row)"
                />
                <span v-else class="text-grey-5">—</span>
              </q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props" auto-width>
                <q-btn
                  flat
                  dense
                  round
                  color="primary"
                  icon="eva-eye-outline"
                  size="sm"
                  @click.stop="goToDetail(props.row)"
                />
              </q-td>
            </template>
          </q-table>

          <div v-if="pagination.lastPage > 1" class="flex justify-center mt-6">
            <q-pagination
              v-model="pagination.page"
              :max="pagination.lastPage"
              :max-pages="6"
              boundary-numbers
              direction-links
              @update:model-value="onPageChange"
            />
          </div>
        </template>
      </div>
    </div>

    <voucherModal
      v-if="voucherDialog"
      :vaucher="voucherDialog"
      :dialog="!!voucherDialog"
      @closeModal="voucherDialog = null"
    />
  </div>
</template>
