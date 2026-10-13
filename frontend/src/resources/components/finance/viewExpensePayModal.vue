<script setup>
import { computed, ref, watch } from 'vue'
import moment from 'moment'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false
  },
  expense: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['closeModal'])

const payStore = usePayStore()
const dialogVisible = ref(props.dialog)
const loading = ref(false)
const payData = ref(null)
const showVoucherModal = ref(false)

const urlMedia = import.meta.env.VITE_LARAVEL_API_URL || ''

const isPdf = (url) => {
  if (!url) return false
  return /\.pdf$/i.test(url)
}

const isImage = (url) => {
  if (!url) return false
  return /\.(jpg|jpeg|png|webp)$/i.test(url)
}

const formatMoney = (value) => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0.00'
  const fixed = n.toFixed(2)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const formatDate = (date) => {
  if (!date) return '—'
  const parsed = moment(date)
  if (!parsed.isValid()) return date
  return parsed.format('DD/MM/YYYY')
}

const fileName = (url) => {
  if (!url) return ''
  return url.split('/').pop() || ''
}

const voucherUrl = computed(() => {
  const path = payData.value?.vaucher
  if (!path) return ''
  if (path.startsWith('http')) return path
  return urlMedia + path
})

const loadPayData = async () => {
  if (!props.expense) return
  if (props.expense.pay) {
    payData.value = props.expense.pay
    return
  }

  const payId = props.expense.pay_id
  if (!payId) {
    payData.value = null
    return
  }

  loading.value = true
  try {
    const res = await payStore.getPayById(payId)
    if (res?.code === 200) {
      payData.value = res.data
    }
  } catch (err) {
    console.error('Error al cargar detalle del pago', err)
    payData.value = null
  } finally {
    loading.value = false
  }
}

const openVoucher = () => {
  if (!voucherUrl.value) return
  if (isPdf(voucherUrl.value)) {
    window.open(voucherUrl.value, '_blank')
  } else {
    showVoucherModal.value = true
  }
}

const close = () => {
  emit('closeModal')
}

watch(
  () => props.dialog,
  (val) => {
    dialogVisible.value = val
    if (val) {
      loadPayData()
    }
  }
)

watch(dialogVisible, (val) => {
  if (!val && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" persistent @hide="close">
    <q-card class="q-py-md q-px-lg" style="min-width: 340px; max-width: 540px; width: 95%; border-radius: 1rem;">
      <div class="row items-center justify-between pb-2" style="border-bottom: 1px solid #e0e0e0;">
        <div class="flex items-center gap-2">
          <q-icon name="eva-checkmark-circle-2" color="positive" size="1.75rem" />
          <div class="text-h6 text-bold text-gray-900">Detalle del pago</div>
        </div>
        <q-btn flat dense round icon="eva-close-outline" color="grey-7" @click="close" />
      </div>

      <div v-if="loading" class="flex flex-center py-10">
        <q-spinner-dots color="primary" size="3rem" />
      </div>

      <div v-else-if="payData" class="q-mt-md">
        <!-- Resumen del gasto -->
        <div class="bg-grey-1 rounded-lg p-3 q-mb-md" style="border: 1px solid #eeeeee;">
          <div class="text-caption text-grey-6 font-semibold">Gasto asociado</div>
          <div class="text-subtitle2 text-bold text-gray-900">
            {{ expense?.provider?.name || 'Proveedor' }}
          </div>
          <div v-if="expense?.invoice_number" class="text-xs text-grey-7">
            Factura N°: <span class="font-medium">{{ expense.invoice_number }}</span>
          </div>
          <div v-if="expense?.description" class="text-xs text-grey-7 line-clamp-2 mt-1">
            {{ expense.description }}
          </div>
        </div>

        <!-- Tarjetas de datos del pago -->
        <div class="row q-col-gutter-sm q-mb-md">
          <div class="col-12 col-md-6">
            <div class="bg-green-50 rounded-lg p-3 text-center" style="border: 1px solid #c8e6c9;">
              <div class="text-caption text-grey-7 font-semibold">Monto pagado</div>
              <div class="text-h6 text-bold text-green-8">
                S/ {{ formatMoney(payData.amount) }}
              </div>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="bg-blue-50 rounded-lg p-3 text-center" style="border: 1px solid #bbdefb;">
              <div class="text-caption text-grey-7 font-semibold">Fecha de pago</div>
              <div class="text-h6 text-bold text-blue-9">
                {{ formatDate(payData.pay_date) }}
              </div>
            </div>
          </div>
        </div>

        <div class="row q-col-gutter-sm q-mb-md text-sm">
          <div class="col-6">
            <div class="text-caption text-grey-6">N° Operación / Referencia</div>
            <div class="font-bold text-gray-800">
              {{ payData.reference || '—' }}
            </div>
          </div>
          <div class="col-6">
            <div class="text-caption text-grey-6">Código de pago</div>
            <div class="font-bold text-gray-800">
              {{ payData.pay_id || ('# ' + payData.id) }}
            </div>
          </div>

          <div v-if="payData.financial_transaction?.financial_account?.name" class="col-12 mt-2">
            <div class="text-caption text-grey-6">Cuenta financiera</div>
            <div class="font-bold text-gray-800">
              {{ payData.financial_transaction.financial_account.name }}
            </div>
          </div>

          <div v-if="payData.financial_transaction?.transaction_category?.name" class="col-12 mt-2">
            <div class="text-caption text-grey-6">Categoría contable</div>
            <div class="font-bold text-gray-800">
              {{ payData.financial_transaction.transaction_category.name }}
            </div>
          </div>
        </div>

        <!-- Comprobante / Voucher -->
        <div class="q-mt-md pt-2" style="border-top: 1px solid #e0e0e0;">
          <div class="text-subtitle2 font-semibold text-gray-800 q-mb-xs">Comprobante de pago</div>

          <div v-if="payData.vaucher" class="bg-grey-1 rounded-lg p-3 flex items-center justify-between" style="border: 1px solid #e0e0e0;">
            <div class="flex items-center gap-2">
              <q-icon
                :name="isPdf(payData.vaucher) ? 'eva-file-text-outline' : 'eva-image-outline'"
                :color="isPdf(payData.vaucher) ? 'negative' : 'primary'"
                size="1.75rem"
              />
              <div>
                <div class="text-xs font-bold text-gray-900">
                  {{ fileName(payData.vaucher) }}
                </div>
                <div class="text-caption text-grey-6">
                  {{ isPdf(payData.vaucher) ? 'Documento PDF' : 'Imagen de voucher' }}
                </div>
              </div>
            </div>

            <div class="flex gap-2">
              <q-btn
                flat
                dense
                color="primary"
                icon="eva-eye-outline"
                label="Ver"
                @click="openVoucher"
              />
              <q-btn
                flat
                dense
                color="primary"
                icon="eva-download-outline"
                :href="voucherUrl"
                download
                target="_blank"
              />
            </div>
          </div>

          <div v-else class="text-caption text-grey-6 italic">
            No se adjuntó comprobante para este pago.
          </div>
        </div>
      </div>

      <div v-else class="py-8 text-center text-grey-7">
        No se encontró la información del pago de este gasto.
      </div>

      <div class="flex justify-end mt-5 pt-3" style="border-top: 1px solid #e0e0e0;">
        <q-btn
          outline
          color="grey-7"
          label="Cerrar"
          style="border-radius: 0.5rem;"
          @click="close"
        />
      </div>
    </q-card>

    <voucherModal
      v-if="payData?.vaucher && !isPdf(payData.vaucher)"
      :vaucher="payData.vaucher"
      :dialog="showVoucherModal"
      @closeModal="showVoucherModal = false"
    />
  </q-dialog>
</template>
