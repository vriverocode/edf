<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import moment from 'moment'
import { usePayStore } from '@/services/store/pay.store'
import voucherModal from '@/components/pay/voucherModal.vue'

const route = useRoute()
const router = useRouter()
const payStore = usePayStore()

const rowKey = route.params.rowKey || ''
const isRefund = rowKey.startsWith('refund-')
const rawId = rowKey.includes('-') ? rowKey.slice(rowKey.indexOf('-') + 1) : ''

const loading = ref(true)
const error = ref(null)
const pay = ref(null)
const refund = ref(null)
const voucherDialog = ref(null)
const voucherTitle = ref('Voucher de pago')

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

const destData = computed(() => {
  const snap = refund.value?.bank_account_snapshot
  if (!snap) return {}
  try {
    return typeof snap.data === 'string' ? JSON.parse(snap.data) : snap.data || {}
  } catch {
    return {}
  }
})

const kind = computed(() => refund.value?.kind || pay.value?.booking?.kind || null)

const kindLabel = computed(() => {
  if (kind.value === 'warranty') return 'Garantía'
  if (kind.value === 'cancellation') return 'Cancelación'
  return null
})

const headerAmount = computed(() => (refund.value ? refund.value.amount : pay.value?.amount))
const statusLabel = computed(() => (refund.value ? 'Completada' : 'Pendiente por devolución'))
const statusColor = computed(() => (refund.value ? 'positive' : 'warning'))

const destLabel = computed(() => {
  const snap = refund.value?.bank_account_snapshot
  if (!snap) return null
  const d = destData.value
  if (d.type === 'yape') return `A su Yape ${d.yape_name || ''} (${d.yape_phone || ''})`
  return `A su cuenta ${d.holder_name || snap.name || ''}${
    d.account_number ? ' — N° ' + d.account_number : ''
  }`
})

const payRows = computed(() => {
  const p = pay.value
  if (!p) return []
  const rows = [
    { label: 'ID de pago', value: p.pay_id ? '#' + p.pay_id : null },
    { label: 'Titular', value: p.user?.name },
    { label: 'Reserva', value: p.booking ? '#' + (p.booking.booking_number ?? '—') : null },
    { label: 'Área', value: p.booking?.comun_area?.name },
    { label: 'Tipo de reserva', value: p.booking ? kindLabel.value : null },
    {
      label: 'Cuotas',
      value: p.quotas?.length ? p.quotas.map((q) => q.month_label).join(', ') : null,
    },
    { label: 'Monto', value: formatMoney(p.amount) },
    {
      label: 'Comisión',
      value: p.commission_amount > 0 ? '- ' + formatMoney(p.commission_amount) : null,
      color: 'text-red-600',
    },
    { label: 'Método de pago', value: p.pay_method?.name },
    { label: 'Fecha de pago', value: p.pay_date ? moment(p.pay_date).format('DD/MM/YYYY') : null },
    { label: 'Referencia bancaria', value: p.reference ? '#' + p.reference : null },
  ]
  return rows.filter((row) => row && row.value)
})

const refundRows = computed(() => {
  const r = refund.value
  if (!r) return []
  const d = destData.value
  const rows = [
    { label: 'Monto devuelto', value: formatMoney(r.amount) },
    { label: 'Tipo', value: kindLabel.value },
    { label: 'Motivo', value: r.reason },
    {
      label: 'Fecha',
      value: r.created_at ? moment(r.created_at).format('DD/MM/YYYY HH:mm') : null,
    },
    { label: 'Destino', value: destLabel.value },
    { label: 'Entidad', value: d.entity },
    { label: 'N° de cuenta', value: d.account_number },
    { label: 'CCI', value: d.cci },
    { label: 'Titular', value: d.holder_name },
    { label: 'Teléfono Yape', value: d.yape_phone },
    { label: 'Nombre Yape', value: d.yape_name },
  ]
  return rows.filter((row) => row && row.value)
})

const openVoucher = (v, title) => {
  if (!v) return
  voucherDialog.value = v
  voucherTitle.value = title
}

const load = async () => {
  loading.value = true
  error.value = null
  try {
    if (!rawId) throw 'Enlace inválido'

    let payId = null
    if (isRefund) {
      refund.value = await payStore.getRefundById(rawId)
      payId = refund.value?.pay_id
    } else {
      payId = Number(rawId)
    }
    if (!payId) throw 'No se encontró el pago asociado a esta devolución'

    const res = await payStore.getPayById(payId)
    pay.value = res?.data || null
    if (!pay.value) throw 'Pago no encontrado'
  } catch (e) {
    error.value = typeof e === 'string' ? e : 'No se pudo cargar la información'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="h-full" style="overflow: hidden">
    <div style="height: 100%; overflow: auto">
      <div class="px-4 pt-4 md:px-36 pb-8">
        <div class="text-center text-black text-h5 text-bold mb-3">Detalle de devolución</div>

        <div v-if="loading" class="flex justify-center items-center py-20">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

        <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-alert-circle-outline" class="text-red-500" size="3rem" />
          </div>
          <h2 class="text-xl font-bold text-gray-900 mb-2">No se pudo cargar</h2>
          <p class="text-gray-600 text-center mb-6">{{ error }}</p>
          <q-btn
            no-caps
            color="primary"
            icon="eva-refresh-outline"
            label="Reintentar"
            style="border-radius: 0.8rem"
            padding="sm lg"
            @click="load"
          />
        </div>

        <template v-else-if="pay">
          <div class="bg-primary text-white rounded-xl p-5 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="text-sm opacity-80 mb-1">Monto</div>
                <div class="text-3xl font-bold">{{ formatMoney(headerAmount) }}</div>
                <div class="text-xs opacity-80 mt-1">
                  {{
                    refund
                      ? moment(refund.created_at).format('DD/MM/YYYY HH:mm')
                      : 'Aún no registrada'
                  }}
                </div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <q-badge :color="statusColor" class="text-white px-3 py-1">{{
                  statusLabel
                }}</q-badge>
                <q-badge
                  v-if="kindLabel"
                  :color="kind === 'warranty' ? 'teal-8' : 'orange'"
                  class="text-white px-3 py-1"
                >
                  {{ kindLabel }}
                </q-badge>
              </div>
            </div>
            <div v-if="pay.title_pay" class="text-xs opacity-70 mt-3">{{ pay.title_pay }}</div>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
            <div
              class="flex justify-between items-center mb-3 pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
            >
              <div class="text-base font-bold text-gray-900 flex items-center gap-2">
                <q-icon name="eva-credit-card-outline" color="primary" size="1.3rem" />
                Pago
              </div>
              <q-badge :color="pay.status_color" class="text-white px-3 py-1">{{
                pay.status_label
              }}</q-badge>
            </div>

            <div class="space-y-3">
              <div
                v-for="row in payRows"
                :key="row.label"
                class="flex justify-between items-start gap-4 pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium shrink-0">{{ row.label }}</span>
                <span class="text-gray-900 font-semibold text-right" :class="row.color">{{
                  row.value
                }}</span>
              </div>
            </div>

            <div class="flex justify-center mt-4">
              <q-btn
                v-if="pay.vaucher"
                outline
                color="primary"
                icon="eva-eye-outline"
                label="Ver voucher del pago"
                style="border-radius: 0.8rem"
                padding="sm lg"
                @click="openVoucher(pay.vaucher, 'Voucher del pago')"
              />
              <q-btn
                v-else
                outline
                disable
                color="grey-6"
                icon="eva-file-outline"
                label="Sin voucher"
                style="border-radius: 0.8rem"
                padding="sm lg"
              />
            </div>
          </div>

          <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
            <div
              class="flex justify-between items-center mb-3 pb-2"
              style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
            >
              <div class="text-base font-bold text-gray-900 flex items-center gap-2">
                <q-icon name="eva-undo-outline" color="orange" size="1.3rem" />
                Devolución
              </div>
              <q-badge :color="statusColor" class="text-white px-3 py-1">{{ statusLabel }}</q-badge>
            </div>

            <template v-if="refund">
              <div class="space-y-3">
                <div
                  v-for="row in refundRows"
                  :key="row.label"
                  class="flex justify-between items-start gap-4 pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium shrink-0">{{ row.label }}</span>
                  <span class="text-gray-900 font-semibold text-right">{{ row.value }}</span>
                </div>
              </div>

              <div class="flex justify-center mt-4">
                <q-btn
                  v-if="refund.vaucher"
                  outline
                  color="primary"
                  icon="eva-eye-outline"
                  label="Ver voucher de la devolución"
                  style="border-radius: 0.8rem"
                  padding="sm lg"
                  @click="openVoucher(refund.vaucher, 'Voucher de la devolución')"
                />
                <q-btn
                  v-else
                  outline
                  disable
                  color="grey-6"
                  icon="eva-file-outline"
                  label="Sin voucher"
                  style="border-radius: 0.8rem"
                  padding="sm lg"
                />
              </div>
            </template>

            <div v-else class="flex flex-col items-center py-6 text-center">
              <div
                class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mb-3"
              >
                <q-icon name="eva-undo-outline" class="text-orange-500" size="2rem" />
              </div>
              <div class="font-semibold text-gray-900">Pendiente por devolución</div>
              <p class="text-sm text-gray-500 mt-1 max-w-md">
                Aún no se ha registrado la devolución de este pago.
              </p>
              <q-btn
                flat
                no-caps
                color="primary"
                icon="eva-arrow-forward-outline"
                label="Gestionar pago"
                class="q-mt-sm"
                @click="router.push(`/admin/pay/validate/${pay.id}`)"
              />
            </div>
          </div>
        </template>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-undo-outline" class="text-gray-400" size="3rem" />
          </div>
          <h2 class="text-xl font-bold text-gray-900 mb-2">Devolución no encontrada</h2>
          <p class="text-gray-600 text-center mb-6">El registro no existe o no tienes permisos.</p>
          <q-btn
            no-caps
            color="primary"
            icon="eva-arrow-back-outline"
            label="Volver a devoluciones"
            style="border-radius: 0.8rem"
            padding="sm lg"
            @click="router.push('/admin/pays/refunds')"
          />
        </div>
      </div>
    </div>

    <voucherModal
      v-if="voucherDialog"
      :vaucher="voucherDialog"
      :dialog="!!voucherDialog"
      :title="voucherTitle"
      @closeModal="voucherDialog = null"
    />
  </div>
</template>
