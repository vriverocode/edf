<script setup>
import { computed, onMounted, ref } from 'vue'
import moment from 'moment'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'

const payStore = usePayStore()

const loading = ref(true)
const refunds = ref([])
const filterUser = ref('')
const filterDept = ref('')
const voucherDialog = ref(null)

const getRefunds = () => {
  loading.value = true
  payStore.getRefunds({ user: filterUser.value, departament: filterDept.value })
    .then((data) => {
      refunds.value = data || []
    })
    .catch(() => {})
    .finally(() => {
      loading.value = false
    })
}

const clearFilters = () => {
  filterUser.value = ''
  filterDept.value = ''
  getRefunds()
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const totalRefunded = computed(() =>
  refunds.value.reduce((sum, r) => sum + Number(r.amount || 0), 0)
)

const openVoucher = (r) => {
  if (!r.vaucher) return
  voucherDialog.value = r.vaucher
}

const kindColor = (kind) => kind === 'warranty' ? 'blue-2' : 'orange-2'
const kindTextColor = (kind) => kind === 'warranty' ? 'blue-9' : 'orange-9'

onMounted(() => {
  getRefunds()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: auto;">
      <div class="px-4 pt-4 md:px-36">
        <div class="text-center text-black text-h5 text-bold mb-3">
          Devoluciones
        </div>

        <!-- Resumen -->
        <div class="bg-primary text-white rounded-xl p-4 mb-4">
          <div class="text-sm opacity-80 mb-1">Total devuelto (listado)</div>
          <div class="text-2xl font-bold">{{ formatMoney(totalRefunded) }}</div>
          <div class="text-xs opacity-70 mt-1">
            {{ refunds.length }} devolució{{ refunds.length !== 1 ? 'nes' : 'n' }}
          </div>
        </div>

        <!-- Filtros -->
        <div class="row q-col-gutter-sm items-center">
          <div class="col-12 col-md-5">
            <q-input dense outlined v-model="filterUser" placeholder="Buscar por usuario (nombre o correo)"
              @keyup.enter="getRefunds" clearable @clear="getRefunds" color="teal">
              <template v-slot:prepend>
                <q-icon name="eva-person-outline" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-5">
            <q-input dense outlined v-model="filterDept" placeholder="Buscar por departamento (número)"
              @keyup.enter="getRefunds" clearable @clear="getRefunds" color="teal">
              <template v-slot:prepend>
                <q-icon name="eva-home-outline" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-md-2">
            <q-btn unelevated no-caps color="primary" icon="eva-search-outline" label="Buscar"
              class="text-bold w-full" style="border-radius: 0.2rem;" @click="getRefunds" />
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
          <p class="text-gray-600 text-center">Ninguna devolución coincide con los filtros aplicados.</p>
        </div>

        <q-table v-else flat bordered :rows="refunds" :columns="[
          { name: 'created_at', label: 'Fecha', field: 'created_at', format: v => v ? moment(v).format('DD/MM/YYYY HH:mm') : '—', align: 'center' },
          { name: 'booking_number', label: 'Reserva', field: 'booking_number', align: 'center' },
          { name: 'user_name', label: 'Usuario', field: 'user_name', align: 'left' },
          { name: 'departament_number', label: 'Depto', field: 'departament_number', align: 'center' },
          { name: 'comun_area', label: 'Área común', field: 'comun_area', align: 'center' },
          { name: 'kind_label', label: 'Tipo', field: 'kind_label', align: 'center' },
          { name: 'amount', label: 'Monto', field: 'amount', format: v => formatMoney(v), align: 'right' },
          { name: 'voucher', label: 'Voucher', field: 'vaucher', align: 'center' },
        ]" row-key="id" hide-pagination virtual-scroll>
          <template #body-cell-kind_label="props">
            <q-td :props="props" auto-width>
              <q-badge :color="kindColor(props.row.kind)" :text-color="kindTextColor(props.row.kind)"
                :label="props.row.kind_label" />
            </q-td>
          </template>
          <template #body-cell-voucher="props">
            <q-td :props="props" auto-width>
              <q-btn v-if="props.row.vaucher" flat dense color="primary" icon="eva-file-outline" label="Ver"
                no-caps size="sm" @click="openVoucher(props.row)" />
              <span v-else class="text-grey-5">—</span>
            </q-td>
          </template>
        </q-table>
      </div>
    </div>

    <voucherModal v-if="voucherDialog" :vaucher="voucherDialog" :dialog="!!voucherDialog"
      @closeModal="voucherDialog = null" />
  </div>
</template>
