<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Notify, date } from 'quasar'
import { useUserStore } from '@/services/store/users.store'
import { useReserveStore } from '@/services/store/reserve.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { usePayStore } from '@/services/store/pay.store'
import { usePayMethodStore } from '@/services/store/payMethod.store'

const router = useRouter()
const userStore = useUserStore()
const reserveStore = useReserveStore()
const quotaStore = useQuotaStore()
const payStore = usePayStore()
const payMethodStore = usePayMethodStore()

const loading = ref(false)
const submitting = ref(false)

// Step 1: User & Type
const selectedUser = ref(null)
const selectedType = ref(null)
const users = ref([])
const userSearch = ref('')
const typeOptions = [
  { label: 'Cuota de mantenimiento', value: 1 },
  { label: 'Reserva de área común', value: 2 },
]

// Step 2a: Month/Year for quotas
const selectedMonth = ref(null)
const selectedYear = ref(null)
const currentYear = new Date().getFullYear()
const monthOptions = Array.from({ length: 12 }, (_, i) => ({
  label: new Date(2000, i).toLocaleString('es-PE', { month: 'long' }),
  value: i + 1,
}))
const yearOptions = Array.from({ length: 5 }, (_, i) => ({
  label: String(currentYear - i),
  value: currentYear - i,
}))

// Step 2b: Pending items
const pendingQuotas = ref([])
const pendingBookings = ref([])
const selectedItem = ref(null)

// Step 3: Payment form
const paymentForm = ref({
  amount: null,
  pay_method: null,
  pay_date: date.formatDate(new Date(), 'YYYY-MM-DD'),
  reference: '',
  vaucher: null,
})

const payMethods = ref([])

const isQuotaType = computed(() => Number(selectedType.value) === 1)
const isReserveType = computed(() => Number(selectedType.value) === 2)

const userFilterFn = (usersList, searchTerm) => usersList.filter(
  (u) => u.name?.toLowerCase().includes(searchTerm.toLowerCase())
)

const loadUsers = async (search) => {
  try {
    const res = await userStore.getUsers({ page: 1, search: search || '', rol: 2 })
    users.value = res.data?.data || res.data || []
  } catch {
    users.value = []
  }
}

const loadPayMethods = async () => {
  try {
    const res = await payMethodStore.getPayMethod()
    payMethods.value = res.data || []
  } catch {
    payMethods.value = []
  }
}

const loadPendingQuotas = async () => {
  if (!selectedUser.value || !selectedMonth.value) return
  pendingQuotas.value = []
  selectedItem.value = null
  try {
    const res = await quotaStore.getQuotaByMonth(selectedMonth.value, {
      year: selectedYear.value || currentYear,
      owner: selectedUser.value.id,
      status: 1,
    })
    pendingQuotas.value = res.data || []
  } catch {
    pendingQuotas.value = []
  }
}

const loadPendingBookings = async () => {
  if (!selectedUser.value) return
  pendingBookings.value = []
  selectedItem.value = null
  try {
    const res = await reserveStore.getReservesByUser({
      status: 1,
      user_id: selectedUser.value.id,
    })
    pendingBookings.value = res.data || []
  } catch {
    pendingBookings.value = []
  }
}

watch(selectedUser, () => {
  selectedItem.value = null
  if (isQuotaType.value && selectedMonth.value) loadPendingQuotas()
  if (isReserveType.value) loadPendingBookings()
})

watch([selectedMonth, selectedYear], () => {
  if (isQuotaType.value && selectedUser.value) loadPendingQuotas()
})

watch(selectedType, () => {
  selectedItem.value = null
  if (isQuotaType.value && selectedUser.value && selectedMonth.value) loadPendingQuotas()
  if (isReserveType.value && selectedUser.value) loadPendingBookings()
})

watch(selectedItem, (item) => {
  if (item) {
    paymentForm.value.amount = Number(item.amount) || 0
  } else {
    paymentForm.value.amount = null
  }
})

const selectItem = (item) => {
  selectedItem.value = item
}

const formatTime = (time) => {
  if (!time) return ''
  const parts = time.split(':')
  return parts.length >= 2 ? `${parts[0]}:${parts[1]}` : time
}

const submitPay = async () => {
  if (!selectedUser.value || !selectedType.value || !selectedItem.value) {
    Notify.create({ color: 'negative', message: 'Completa todos los campos requeridos' })
    return
  }
  if (!paymentForm.value.amount || paymentForm.value.amount <= 0) {
    Notify.create({ color: 'negative', message: 'El monto debe ser mayor a cero' })
    return
  }
  if (!paymentForm.value.pay_method) {
    Notify.create({ color: 'negative', message: 'Selecciona un método de pago' })
    return
  }

  const formData = new FormData()
  formData.append('user_id', selectedUser.value.id)
  formData.append('type', selectedType.value)
  formData.append('to_pay_id', selectedItem.value.id)
  formData.append('amount', paymentForm.value.amount)
  formData.append('pay_method', paymentForm.value.pay_method)
  formData.append('pay_date', paymentForm.value.pay_date)
  formData.append('reference', paymentForm.value.reference || '000000')
  if (paymentForm.value.vaucher) {
    formData.append('vaucher', paymentForm.value.vaucher)
  }

  submitting.value = true
  try {
    const res = await payStore.createPay(formData)
    Notify.create({ color: 'positive', message: 'Pago registrado correctamente' })
    router.push('/admin/pay/validate/' + res.data.idPay)
  } catch (err) {
    const msg = typeof err?.message === 'string' ? err.message : 'Error al registrar el pago'
    Notify.create({ color: 'negative', message: msg })
  } finally {
    submitting.value = false
  }
}

loadPayMethods()
</script>

<template>
  <div class="h-full relative" style="overflow:hidden">
    <div class="relative z-10 pt-0 px-6 h-full" style="overflow:auto">
      <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-3xl p-6 mx-auto my-6">
        <h2 class="text-lg font-bold text-gray-900 mb-6">Registrar pago</h2>

        <!-- Step 1: User & Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Propietario</div>
            <q-select
              v-model="selectedUser"
              :options="users"
              option-label="name"
              option-value="id"
              label="Buscar propietario..."
              use-input
              fill-input
              input-debounce="300"
              clearable
              dense
              class="form__inputsR_registePay"
              @filter="(search, update) => { userSearch = search; loadUsers(search); update() }"
              @filter-abort="loadUsers('')"
            >
              <template v-slot:option="{ itemProps, opt }">
                <q-item v-bind="itemProps" dense>
                  <q-item-section avatar class="min-w-[36px]">
                    <q-avatar size="28px" color="primary" text-color="white" class="text-xs font-bold">
                      {{ (opt.name || '?')[0] }}
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-sm">{{ opt.name }}</q-item-label>
                    <q-item-label caption class="text-xs">{{ opt.email }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Tipo de pago</div>
            <q-select
              v-model="selectedType"
              :options="typeOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              placeholder="Seleccionar tipo"
              clearable
              dense
              class="form__inputsR_registePay"
            />
          </div>
        </div>

        <!-- Step 2a: Month picker for quotas -->
        <div v-if="isQuotaType" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Mes</div>
            <q-select
              v-model="selectedMonth"
              :options="monthOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              placeholder="Seleccionar mes"
              clearable
              dense
              class="form__inputsR_registePay"
            />
          </div>
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Año</div>
            <q-select
              v-model="selectedYear"
              :options="yearOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              :placeholder="String(currentYear)"
              clearable
              dense
              class="form__inputsR_registePay"
            />
          </div>
        </div>

        <!-- Step 2b: Pending items list -->
        <div v-if="isQuotaType && pendingQuotas.length" class="mb-6">
          <div class="text-sm font-medium text-gray-700 mb-2">Cuotas pendientes</div>
          <div class="space-y-2">
            <div
              v-for="quota in pendingQuotas"
              :key="quota.id"
              class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-colors"
              :class="selectedItem?.id === quota.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300'"
              @click="selectItem(quota)"
            >
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ quota.month_label }}</div>
                <div class="text-xs text-gray-500">Unidad {{ quota.departament?.number || '—' }}</div>
              </div>
              <div class="text-right">
                <div class="text-sm font-bold text-gray-900">S/. {{ Number(quota.amount).toFixed(2) }}</div>
                <q-badge :color="quota.status === 1 ? 'warning' : 'grey'" class="text-xs">
                  {{ quota.status_label || (quota.status === 1 ? 'Pendiente' : '') }}
                </q-badge>
              </div>
            </div>
          </div>
        </div>

        <div v-if="isReserveType && pendingBookings.length" class="mb-6">
          <div class="text-sm font-medium text-gray-700 mb-2">Reservas pendientes de pago</div>
          <div class="space-y-2">
            <div
              v-for="booking in pendingBookings"
              :key="booking.id"
              class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-colors"
              :class="selectedItem?.id === booking.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300'"
              @click="selectItem(booking)"
            >
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ booking.comun_area?.name || 'Área común' }}</div>
                <div class="text-xs text-gray-500">
                  {{ booking.date }} | {{ formatTime(booking.time_from) }} - {{ formatTime(booking.time_to) }}
                </div>
              </div>
              <div class="text-right">
                <div class="text-sm font-bold text-gray-900">S/. {{ Number(booking.amount).toFixed(2) }}</div>
                <q-badge color="warning" class="text-xs">Pendiente</q-badge>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty states -->
        <div v-if="isQuotaType && selectedUser && selectedMonth && !pendingQuotas.length && !loading" class="text-center py-6 text-gray-400 text-sm">
          No hay cuotas pendientes para este propietario y mes.
        </div>
        <div v-if="isReserveType && selectedUser && !pendingBookings.length && !loading" class="text-center py-6 text-gray-400 text-sm">
          No hay reservas pendientes de pago para este propietario.
        </div>

        <!-- Step 3: Payment form -->
        <div v-if="selectedItem" class="border-t border-gray-200 pt-6 mt-6">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Detalles del pago</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Monto *</div>
              <q-input
                v-model="paymentForm.amount"
                type="number"
                step="0.01"
                placeholder="0.00"
                dense
                class="form__inputsR_registePay"
                prefix="S/."
              />
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Método de pago *</div>
              <q-select
                v-model="paymentForm.pay_method"
                :options="payMethods"
                option-label="name"
                option-value="id"
                emit-value
                map-options
                placeholder="Seleccionar método"
                clearable
                dense
                class="form__inputsR_registePay"
              />
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Fecha de pago *</div>
              <q-input
                v-model="paymentForm.pay_date"
                type="date"
                dense
                class="form__inputsR_registePay"
              />
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">N° de referencia / operación</div>
              <q-input
                v-model="paymentForm.reference"
                placeholder="000000"
                dense
                class="form__inputsR_registePay"
              />
            </div>
          </div>
          <div class="mt-4">
            <div class="text-sm font-medium text-gray-700 mb-1">Voucher / comprobante</div>
            <q-file
              v-model="paymentForm.vaucher"
              label="Seleccionar imagen"
              accept="image/*"
              dense
              class="form__inputsR_registePay"
              clearable
            >
              <template v-slot:prepend>
                <q-icon name="eva-attach-2-outline" />
              </template>
            </q-file>
          </div>

          <div class="flex justify-end gap-3 mt-6">
            <q-btn
              label="Cancelar"
              flat
              color="grey"
              no-caps
              @click="router.push('/')"
            />
            <q-btn
              label="Registrar pago"
              unelevated
              color="primary"
              no-caps
              :loading="submitting"
              @click="submitPay"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.form__inputsR_registePay {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    padding: 0px 1rem;
  }
}

@media (max-width: 780px) {
  .form__inputsR_registePay {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
