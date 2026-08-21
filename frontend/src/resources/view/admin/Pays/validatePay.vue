<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import { usePayStore } from '@/services/store/pay.store'
import { useReserveStore } from '@/services/store/reserve.store'
import { useTransactionCategoryStore } from '@/services/store/transactionCategory.store'
import { useBankAccountStore } from '@/services/store/bankAccount.store'
import moment from 'moment'
import voucherModal from '@/components/pay/voucherModal.vue'
import createTransactionCategoryModal from '@/components/finance/createTransactionCategoryModal.vue'
import createFinancialAccountModal from '@/components/finance/createFinancialAccountModal.vue'
import { Notify, Dialog } from 'quasar'
import ApiService from '@/services/axios'

const route = useRoute()
const router = useRouter()
const payStore = usePayStore()
const reserveStore = useReserveStore()
const transactionCategoryStore = useTransactionCategoryStore()
const bankStore = useBankAccountStore()
const dialog = ref('')

const refundList = ref([])
const loadingRefunds = ref(false)

const loadRefundableBookings = async () => {
  loadingRefunds.value = true
  try {
    const res = await reserveStore.getReservesByUser({ status: -1, per_page: 999 })
    if (res?.code === 200) {
      const data = Array.isArray(res.data) ? res.data : (res.data?.data || [])
      refundList.value = data.filter(r =>
        r.amount > 0
        && r.pay
        && [0, 6].includes(r.status)
        && [2, 6].includes(r.pay.status)
      )
    }
  } catch {
    refundList.value = []
  } finally {
    loadingRefunds.value = false
  }
}

const refundAmount = (b) => {
  if (b.kind === 'warranty') {
    const warrantyPrice = Number(b.comun_area?.warranty_price ?? 0)
    if (warrantyPrice > 0) return warrantyPrice
  }
  return Number(b.amount ?? 0)
}

const refundOriginLabel = (b) => (b.kind === 'warranty' ? 'Garantía' : 'Cancelación')

const refundDialog = ref(false)
const refundTarget = ref(null)
const refundAccounts = ref([])
const refundLoadingAccounts = ref(false)
const refundAccountId = ref(null)
const refundVaucher = ref(null)
const refundSubmitting = ref(false)
const notifyingUser = ref(false)

const parseAccountData = (data) => {
  try {
    return typeof data === 'string' ? JSON.parse(data) : (data || {})
  } catch {
    return {}
  }
}

const refundAccountLabel = (acc) => {
  const d = parseAccountData(acc.data)
  if (d.type === 'yape') {
    return `${acc.name} — Yape ${d.yape_name || ''} (${d.yape_phone || ''})`
  }
  return `${acc.name} — ${d.holder_name || ''} ${d.account_number ? `— Cuenta ${d.account_number}` : ''}`
}

const selectedAccountData = computed(() => {
  if (!refundAccountId.value) return null
  const acc = refundAccounts.value.find(a => a.id === refundAccountId.value)
  if (!acc) return null
  return { ...parseAccountData(acc.data), name: acc.name }
})


const openRefundDialog = async (booking) => {
  refundTarget.value = booking
  refundAccountId.value = null
  refundVaucher.value = null
  refundDialog.value = true
  refundLoadingAccounts.value = true
  refundAccounts.value = []
  try {
    const res = await bankStore.getAccountsByUser(booking.user_id)
    refundAccounts.value = Array.isArray(res.data) ? res.data : (res.data?.data || [])
  } catch {
    refundAccounts.value = []
  } finally {
    refundLoadingAccounts.value = false
  }
}

const notifyUserForBankAccount = async () => {
  if (!refundTarget.value) return
  notifyingUser.value = true
  try {
    await ApiService.post('/api/pays/refund/notify-missing-bank-account', {
      booking_id: refundTarget.value.id,
    })
    showNotify('positive', 'Notificación enviada al usuario')
  } catch (e) {
    showNotify('negative', e?.response?.data?.error || 'Error al notificar al usuario')
  } finally {
    notifyingUser.value = false
  }
}

const submitRefund = async () => {
  const booking = refundTarget.value
  if (!booking || !refundAccountId.value || !refundVaucher.value) return
  refundSubmitting.value = true
  try {
    const formData = new FormData()
    formData.append('booking_id', booking.id)
    formData.append('pay_id', booking.pay.id)
    formData.append('amount', refundAmount(booking))
    formData.append('bank_account_id', refundAccountId.value)
    formData.append('vaucher', refundVaucher.value)
    await ApiService.post('/api/pays/refund', formData)
    showNotify('positive', 'Devolución registrada correctamente')
    refundDialog.value = false
    loadRefundableBookings()
  } catch (e) {
    showNotify('negative', e?.response?.data?.error || 'Error al registrar devolución')
  } finally {
    refundSubmitting.value = false
  }
}

onMounted(() => {
  loadRefundableBookings()
})

const pay = ref(null)
const loading = ref(false)
const ready = ref(true)
const error = ref(null)

const approveDialog = ref(false)
const financialAccounts = ref([])
const transactionCategories = ref([])
const approveLoading = ref(false)
const createCategoryDialog = ref(false)
const createAccountDialog = ref(false)
const approveForm = ref({
  financial_account_id: null,
  transaction_category_id: null,
})

const financialAccountOptions = computed(() =>
  financialAccounts.value.map((a) => ({
    label: `${a.name} (${a.currency?.symbol ?? ''})`.trim(),
    value: a.id,
  }))
)
const categoryOptions = computed(() =>
  transactionCategories.value.map((c) => ({
    label: c.name,
    value: c.id,
  }))
)

const payId = route.params.id || route.query.id

const isQuotaPay = computed(() => Number(pay.value?.type) === 1)
const isReservePay = computed(() => Number(pay.value?.type) === 2)
const isPendingValidation = computed(() => Number(pay.value?.status) === 1)

const getPayById = async (id) => {
  try {
    error.value = null
    const response = await payStore.getPayById(id)
    pay.value = response.data
  } catch (err) {
    console.error('Error al obtener el pago:', err)
    error.value = err || 'Error al cargar el pago'
  } finally {
    ready.value = false
  }
}

onMounted(() => {
  if (payId) {
    getPayById(payId)
  } else {
    error.value = 'ID de pago no proporcionado'
    ready.value = false
  }
})

const reloadPay = () => {
  ready.value = true
  if (payId) {
    getPayById(payId)
  }
}

const goToHome = () => {
  router.push('/')
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2200,
  })
}

const loadTransactionCategories = async () => {
  const catRes = await transactionCategoryStore.getTransactionCategories(1)
  transactionCategories.value = catRes.data || []
}

const loadApproveOptions = async () => {
  try {
    ApiService.setHeader()
    const [accRes] = await Promise.all([
      ApiService.get('/api/financial-accounts'),
      loadTransactionCategories(),
    ])
    const accData = accRes.data
    financialAccounts.value = accData?.code === 200 ? accData.data : []
  } catch (e) {
    financialAccounts.value = []
    transactionCategories.value = []
    showNotify('negative', 'No se pudieron cargar cuentas o categorías contables.')
  }
}

const onAccountCreated = (created) => {
  if (!created?.id) return
  const exists = financialAccounts.value.some((a) => a.id === created.id)
  if (!exists) {
    financialAccounts.value = [
      ...financialAccounts.value,
      created,
    ].sort((a, b) => a.name.localeCompare(b.name, 'es'))
  }
  approveForm.value.financial_account_id = created.id
}

const onCategoryCreated = (created) => {
  if (!created?.id) return
  const exists = transactionCategories.value.some((c) => c.id === created.id)
  if (!exists) {
    transactionCategories.value = [
      ...transactionCategories.value,
      created,
    ].sort((a, b) => a.name.localeCompare(b.name, 'es'))
  }
  approveForm.value.transaction_category_id = created.id
}

const openApproveDialog = async () => {
  approveForm.value = {
    financial_account_id: null,
    transaction_category_id: null,
  }
  await loadApproveOptions()
  approveDialog.value = true
}

const confirmReject = () => {
  Dialog.create({
    title: 'Rechazar pago',
    message: '¿Confirmas rechazar este pago? Esta acción notificará al residente donde aplique.',
    cancel: true,
    persistent: true,
    ok: { label: 'Rechazar', color: 'negative', flat: true },
  }).onOk(() => {
    submitReject()
  })
}

const submitReject = () => {
  if (!pay.value?.id) return
  loading.value = true
  payStore
    .validatePayment({
      id: pay.value.id,
      data: { status: 3 },
    })
    .then(() => {
      showNotify('warning', 'Pago rechazado.')
      setTimeout(() => router.go(-1), 900)
    })
    .catch((err) => {
      const msg =
        typeof err?.message === 'string'
          ? err.message
          : typeof err?.error?.message === 'string'
            ? err.error.message
            : typeof err?.error === 'string'
              ? err.error
              : 'No se pudo rechazar.'
      showNotify('negative', String(msg))
    })
    .finally(() => {
      loading.value = false
    })
}

const submitApprove = () => {
  if (!approveForm.value.financial_account_id || !approveForm.value.transaction_category_id) {
    showNotify('warning', 'Selecciona cuenta y categoría contable.')
    return
  }
  if (!pay.value?.id) return
  approveLoading.value = true
  payStore
    .validatePayment({
      id: pay.value.id,
      data: {
        status: 2,
        financial_account_id: approveForm.value.financial_account_id,
        transaction_category_id: approveForm.value.transaction_category_id,
      },
    })
    .then(() => {
      approveDialog.value = false
      showNotify('positive', 'Pago aprobado y registrado contablemente.')
      setTimeout(() => router.go(-1), 900)
    })
    .catch((err) => {
      const msg =
        typeof err?.message === 'string'
          ? err.message
          : typeof err?.error?.message === 'string'
            ? err.error.message
            : typeof err?.error === 'string'
              ? err.error
              : 'No se pudo aprobar el pago.'
      showNotify('negative', String(msg))
    })
    .finally(() => {
      approveLoading.value = false
    })
}

const showModal = () => {
  dialog.value = 'voucher'
}
</script>

<template>
  <div class="h-full relative " style="overflow:hidden">
    <div class="relative z-10 pt-0 px-6 h-full" style="overflow:auto">
      <div v-if="ready" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium">Cargando pago...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">No se pudo cargar</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <button @click="reloadPay"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors">
          Reintentar
        </button>
      </div>

      <div v-else-if="pay" class="h-full">
        <div
          class="bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-sm md:max-w-4xl p-6 pb-4 mx-auto ">
          <div class="flex justify-between items-start mb-3">
            <h2 class="text-lg font-bold text-gray-900 m-0">{{ pay.title_pay }}</h2>
            <q-badge :color="pay.status_color" class="text-white px-3 py-1">
              {{ pay.status_label }}
            </q-badge>
          </div>

          <div class="space-y-4">
            <!-- Detalles del pago -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);" v-if="pay.type == 2 && pay.booking">
              <span class="text-gray-600 font-medium">Area</span>
              <span class="text-gray-900 font-semibold">{{ pay.booking.comun_area.name }}</span>
            </div>
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);" v-if="pay.type == 1 && pay.quotas?.length">
              <span class="text-gray-600 font-medium">Cuota(s) pagada(s)</span>
              <span class="text-gray-900 font-semibold flex flex-wrap justify-end gap-1 items-center">
                <span class="bg-primary text-white text-xs px-2 py-1 rounded-md" v-for="quota in pay.quotas"
                  :key="quota.id">
                  {{ quota.month_label }} (Unidad {{ quota.departament?.number }})
                </span>
              </span>
            </div>
            <!-- ID de transacción -->
            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">ID de pago</span>
              <span class="text-gray-900 font-semibold">#{{ pay.pay_id }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Titular</span>
              <span class="text-gray-900 font-semibold">{{ pay.user?.name }}</span>
            </div>

            <div v-if="isReservePay" class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Reserva</span>
              <span class="text-gray-900 font-semibold">#{{ pay.booking?.booking_number ?? '—' }}</span>
            </div>

            <div class="flex justify-between items-center pb-2" v-if="isQuotaPay && pay.quota"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Cuota referencia</span>
              <span class="text-gray-900 font-semibold">{{ pay.quota?.month_label }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Monto reportado</span>
              <span class="text-gray-900 font-semibold">S/. {{ pay.amount.toFixed(2) }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Fecha de pago</span>
              <span class="text-gray-900 font-semibold">{{ moment(pay.pay_date).format('DD/MM/YYYY') }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Método de pago</span>
              <span class="text-gray-900 font-semibold">
                {{ pay.pay_method?.name }}
              </span>
            </div>
            <!-- User -->
            <div class="flex justify-between items-center pb-2" v-if="pay.user"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Referencia bancaria</span>
              <span class="text-gray-900 font-semibold">#{{ pay.reference }}</span>
            </div>
          </div>

          <template v-if="isQuotaPay && pay.consolidated_quotas?.length">
            <div class="mt-4">
              <div class="text-subtitle2 text-grey-9 q-mb-sm">Cuotas incluidas en este pago</div>
              <q-markup-table flat bordered dense wrap-cells class="rounded-borders">
                <thead>
                  <tr>
                    <th class="text-left">Unidad</th>
                    <th class="text-right">Mes</th>
                    <th class="text-right">Importe</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="q in pay.consolidated_quotas" :key="q.id">
                    <td>{{ q.departament?.number ?? '—' }}</td>
                    <td class="text-right">{{ q.month_label }}</td>
                    <td class="text-right">S/. {{ q.amount }}</td>
                  </tr>
                </tbody>
              </q-markup-table>
            </div>
          </template>

          <div class="flex flex-center mt-4 cursor-pointer" @click="showModal"
            v-if="pay.pay_method != 3 && pay.vaucher">
            <div class="text-center text-subtitle1 text-primary text-bold font-medium text__vaucher">
              Ver comprobante (voucher)
            </div>
            <span class="ml-2" v-html="iconsApp.voucher"></span>
          </div>
        </div>

        <!-- Refund section -->
        <div v-if="refundList.length > 0 && pay.booking.status == 5" class="q-mt-md">
          <div class="text-subtitle1 text-bold text-black q-mb-sm">Devoluciones pendientes</div>
          <div v-for="b in refundList" :key="b.id"
            class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden q-mb-sm q-pa-md">
            <div class="flex justify-between items-center">
              <div>
                <div class="text-body2 text-bold">#{{ b.booking_number }} — {{ b.comun_area?.name }}</div>
                <div class="text-caption text-grey-7">{{ b.user?.name }} — S/. {{ refundAmount(b).toFixed(2) }}</div>
              </div>
              <div class="flex items-center gap-2">
                <q-badge :color="b.kind === 'warranty' ? 'teal-8' : 'orange'" class="text-white px-2 py-1">
                  {{ refundOriginLabel(b) }}
                </q-badge>
                <q-btn color="orange" unelevated size="sm" style="border-radius: 0.5rem;"
                  @click="openRefundDialog(b)">
                  <q-icon name="eva-undo-outline" class="q-mr-xs" />
                  Devolver
                </q-btn>
              </div>
            </div>
          </div>
        </div>

        <div v-if="isPendingValidation" class="flex flex-wrap justify-center gap-3 mt-4 pb-12 ">
          <q-btn label="Rechazar pago" unelevated outline color="negative" style="border-radius: 0.8rem;"
            padding="sm lg" @click="confirmReject" :loading="loading" />
          <q-btn label="Aprobar" unelevated color="primary" style="border-radius: 0.8rem;" padding="sm lg"
            :loading="loading" @click="openApproveDialog" />
        </div>
        <p v-else class="text-grey-7 text-caption q-mt-md">Este pago ya tiene un resultado de validación.</p>

        <voucherModal :vaucher="pay.vaucher" :dialog="(dialog === 'voucher')" @closeModal="dialog = ''" />
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Pago no encontrado</h2>
        <p class="text-gray-600 text-center mb-6">El pago no existe o no tienes permisos.</p>
        <button @click="goToHome"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors">
          Volver al inicio
        </button>
      </div>
    </div>

    <q-dialog v-model="approveDialog">
      <q-card style="min-width: min(440px, 92vw);" class="q-pa-md">
        <div class="text-h6 q-mb-sm">Registrar ingreso</div>
        <div class="text-caption text-grey-7 q-mb-md">
          Para generar un asociación contable ingresa los siguientes datos
        </div>
        <div class="q-mt-sm">
          <div class="row q-col-gutter-sm items-end">
            <div class="col">
              <div class="text-subtitle2 text-black">Cuenta financiera</div>
              <q-select dense borderless emit-value map-options class="form__inputsR mt-1" color="primary"
                v-model="approveForm.financial_account_id" :options="financialAccountOptions" />
            </div>
            <div class="col-auto">
              <q-btn flat dense round color="primary" @click="createAccountDialog = true">
                <q-icon name="eva-plus-outline" />
                <q-tooltip>Nueva cuenta</q-tooltip>
              </q-btn>
            </div>
          </div>
        </div>
        <div class="row q-col-gutter-sm q-mt-md items-end">
          <div class="col">
            <div class="text-subtitle2 text-black">Categoría de la transacción</div>
            <q-select dense borderless emit-value map-options class="form__inputsR mt-1" color="primary"
              v-model="approveForm.transaction_category_id" :options="categoryOptions" />
          </div>
          <div class="col-auto">
            <q-btn flat dense round color="primary" @click="createCategoryDialog = true">
              <q-icon name="eva-plus-outline" />
              <q-tooltip>Nueva categoría</q-tooltip>
            </q-btn>
          </div>
        </div>
        <div class="row justify-end q-gutter-sm q-mt-lg">
          <q-btn flat label="Cancelar" v-close-popup color="grey" no-caps />
          <q-btn color="primary" label="Confirmar aprobación" no-caps :loading="approveLoading"
            @click="submitApprove" />
        </div>
      </q-card>
    </q-dialog>

    <createTransactionCategoryModal :dialog="createCategoryDialog" :default-type="1"
      @close-modal="createCategoryDialog = false" @created="onCategoryCreated" />
    <createFinancialAccountModal :dialog="createAccountDialog"
      @close-modal="createAccountDialog = false" @created="onAccountCreated" />

    <q-dialog v-model="refundDialog">
      <q-card style="min-width: min(440px, 92vw);" class="q-pa-md">
        <div class="flex items-center justify-between q-mb-sm">
          <div class="text-h6">Registrar devolución</div>
          <q-btn flat round dense icon="eva-close-outline" v-close-popup />
        </div>
        <div v-if="refundTarget" class="text-caption text-grey-7 q-mb-md">
          #{{ refundTarget.booking_number }} — {{ refundTarget.comun_area?.name }} ·
          {{ refundTarget.user?.name }} · <b>S/. {{ refundAmount(refundTarget).toFixed(2) }}</b>
        </div>

        <template v-if="refundLoadingAccounts">
          <div class="flex flex-center q-py-md">
            <q-spinner color="primary" size="sm" />
            <span class="q-ml-sm text-caption text-grey-7">Cargando cuentas...</span>
          </div>
        </template>

        <template v-else-if="refundAccounts.length > 0">
          <div class="text-subtitle2 text-black">Cuenta bancaria / Yape del usuario</div>
          <q-select dense borderless emit-value map-options class="form__inputsR mt-1" color="primary"
            v-model="refundAccountId" :options="refundAccounts.map(a => ({ label: refundAccountLabel(a), value: a.id }))" />

          <div v-if="selectedAccountData" class="q-mt-sm bg-grey-2 p-3 rounded-lg border border-gray-200">
            <table v-if="selectedAccountData.type === 'yape'" class="w-full text-sm">
              <tbody>
                <tr>
                  <td class="font-medium text-gray-600 py-1 w-1/3">Nombre:</td>
                  <td class="text-gray-900">{{ selectedAccountData.yape_name || selectedAccountData.name }}</td>
                </tr>
                <tr>
                  <td class="font-medium text-gray-600 py-1">Teléfono:</td>
                  <td class="text-gray-900">{{ selectedAccountData.yape_phone }}</td>
                </tr>
              </tbody>
            </table>
            <table v-else class="w-full text-sm">
              <tbody>
                <tr>
                  <td class="font-medium text-gray-600 py-1 w-1/3">Banco:</td>
                  <td class="text-gray-900">{{ selectedAccountData.name }}</td>
                </tr>
                <tr>
                  <td class="font-medium text-gray-600 py-1">Titular:</td>
                  <td class="text-gray-900">{{ selectedAccountData.holder_name }}</td>
                </tr>
                <tr v-if="selectedAccountData.account_number">
                  <td class="font-medium text-gray-600 py-1">Cuenta:</td>
                  <td class="text-gray-900">{{ selectedAccountData.account_number }}</td>
                </tr>
                <tr v-if="selectedAccountData.cci">
                  <td class="font-medium text-gray-600 py-1">CCI:</td>
                  <td class="text-gray-900">{{ selectedAccountData.cci }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="text-subtitle2 text-black q-mt-md">Voucher de la devolución</div>
          <div class="mt-1">
            <label class="file__input border rounded-lg px-3 py-2 flex items-center justify-between cursor-pointer"
              :class="refundVaucher ? 'border-green-500 text-green-600' : 'border-gray-300 text-gray-500'">
              <span class="text-sm truncate">{{ refundVaucher?.name || 'Seleccionar imagen del voucher' }}</span>
              <q-icon :name="refundVaucher ? 'eva-checkmark-circle-2-outline' : 'eva-attach-outline'" />
              <input type="file" accept="image/*" class="hidden" @change="refundVaucher = $event.target.files[0] || null" />
            </label>
          </div>
        </template>

        <template v-else>
          <div class="bg-orange-50 border border-orange-200 rounded-lg p-3 q-mb-md">
            <div class="text-caption text-orange-800">
              Este usuario aún no registra una cuenta bancaria o Yape. No es posible registrar la devolución.
            </div>
            <q-btn flat dense color="orange" no-caps class="q-mt-sm" icon="eva-email-outline"
              :loading="notifyingUser" @click="notifyUserForBankAccount">
              Notificar al usuario
            </q-btn>
          </div>
        </template>

        <div class="row justify-end q-gutter-sm q-mt-lg">
          <q-btn flat label="Cancelar" v-close-popup color="grey" no-caps />
          <q-btn color="orange" label="Devolver" no-caps :loading="refundSubmitting"
            :disable="!refundAccountId || !refundVaucher" @click="submitRefund" />
        </div>
      </q-card>
    </q-dialog>
  </div>
</template>

<style lang="scss">
.text__vaucher {
  transition: all 0.5s ease;

  &:hover {
    text-decoration: underline;
  }
}

.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

@media (max-width: 780px) {
  .form__inputsR {
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}
</style>
