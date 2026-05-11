<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import iconsApp from '@/assets/icons/index'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'
import { Notify, Dialog } from 'quasar'
import ApiService from '@/services/axios'

const route = useRoute()
const router = useRouter()
const payStore = usePayStore()
const dialog = ref('')

const pay = ref(null)
const loading = ref(false)
const ready = ref(true)
const error = ref(null)

const approveDialog = ref(false)
const financialAccounts = ref([])
const transactionCategories = ref([])
const approveLoading = ref(false)
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

const loadApproveOptions = async () => {
  try {
    ApiService.setHeader()
    const [accRes, catRes] = await Promise.all([
      ApiService.get('/api/financial-accounts'),
      ApiService.get('/api/transaction-categories?type=1'),
    ])
    const accData = accRes.data
    const catData = catRes.data
    financialAccounts.value = accData?.code === 200 ? accData.data : []
    transactionCategories.value = catData?.code === 200 ? catData.data : []
  } catch (e) {
    financialAccounts.value = []
    transactionCategories.value = []
    showNotify('negative', 'No se pudieron cargar cuentas o categorías contables.')
  }
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
  <div class="h-full relative overflow-auto">
    <div class="relative z-10 pt-5 pb-2 px-6 h-full">
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

      <div v-else-if="pay" class="flex flex-col items-center h-full">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full max-w-sm md:max-w-4xl p-6 pb-4 mb-4">
          <div class="flex justify-between items-start mb-3">
            <h2 class="text-lg font-bold text-gray-900 m-0">{{ pay.title_pay }}</h2>
            <q-badge :color="pay.status_color" class="text-white px-3 py-1">
              {{ pay.status_label }}
            </q-badge>
          </div>

          <div class="space-y-4">
            <div v-if="isReservePay" class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Área</span>
              <span class="text-gray-900 font-semibold">{{ pay.booking?.comun_area?.name ?? '—' }}</span>
            </div>

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
              <span class="text-gray-900 font-semibold">S/. {{ pay.amount }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Fecha de pago</span>
              <span class="text-gray-900 font-semibold">{{ new Date(pay.pay_date).toLocaleDateString('es-PE') }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534);">
              <span class="text-gray-600 font-medium">Método</span>
              <span class="text-gray-900 font-semibold">{{ pay.pay_method?.name }}</span>
            </div>

            <div class="flex justify-between items-center pb-2"
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

        <div v-if="isPendingValidation" class="flex flex-wrap justify-center gap-3 mt-4">
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
          Asocia este cobro con la cuenta financiera donde ingresa el efectivo / banco y la categoría del ERP.
        </div>
        <q-select outlined dense emit-value map-options behavior="dialog" v-model="approveForm.financial_account_id"
          :options="financialAccountOptions" label="Cuenta financiera" />
        <q-select class="q-mt-md" outlined dense emit-value map-options behavior="dialog"
          v-model="approveForm.transaction_category_id" :options="categoryOptions"
          label="Categoría de la transacción" />
        <div class="row justify-end q-gutter-sm q-mt-lg">
          <q-btn flat label="Cancelar" v-close-popup color="grey" />
          <q-btn color="primary" label="Confirmar aprobación" :loading="approveLoading" @click="submitApprove" />
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
</style>
