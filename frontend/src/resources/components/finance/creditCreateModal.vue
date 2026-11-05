<script setup>
import { ref, watch, computed } from 'vue'
import { usePayStore } from '@/services/store/pay.store'
import { Notify } from 'quasar'
import moment from 'moment'

const props = defineProps({
  modelValue: Boolean,
})

const emit = defineEmits(['update:modelValue', 'created'])

const payStore = usePayStore()
const loading = ref(false)
const loadingPayments = ref(false)
const payments = ref([])
const selectedPayment = ref(null)
const creditAmount = ref('')
const description = ref('')
const currentMonth = new Date().getMonth() + 1
const currentYear = new Date().getFullYear()
const applicableMonth = ref(currentMonth)
const applicableYear = ref(currentYear)

const show = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const maxAmount = computed(() => {
  if (!selectedPayment.value) return 0
  return Number(selectedPayment.value.amount || 0)
})

const remainingAvailable = computed(() => {
  if (!selectedPayment.value) return 0
  const alreadyApplied = Number(selectedPayment.value.credit_applied || 0)
  return maxAmount.value - alreadyApplied
})

const paymentOptionLabel = (pay) => {
  return `${pay.user_name} - DPT ${pay.departament_numbers}`
}

const paymentOptionSub = (pay) => {
  const statusText = pay.status === 3 ? 'Completado' : 'Pendiente'
  const payDate = pay.pay_date ? moment(pay.pay_date).format('DD/MM/YYYY') : '—'
  return `S/. ${Number(pay.amount).toFixed(2)} | Ref: ${pay.reference} | ${statusText} | Pago: ${payDate}`
}

const MONTH_LABELS = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
]

// Mes al que aplica el saldo: siempre un mes/año concreto (sin opción flexible).
const monthOptions = MONTH_LABELS.map((label, i) => ({ label, value: i + 1 }))

const yearOptions = [
  { label: String(currentYear), value: currentYear },
  { label: String(currentYear + 1), value: currentYear + 1 },
  { label: String(currentYear + 2), value: currentYear + 2 },
]

const assignedLabel = (a) => {
  const m = Number(a.month || 0)
  const year = a.year ? ` ${a.year}` : ''
  return `${MONTH_LABELS[m - 1] || m}${year}`
}

const formatAssignedMonths = (assigned) => {
  if (!Array.isArray(assigned) || !assigned.length) return ''
  const unique = [...new Set(assigned.map((a) => assignedLabel(a)))]
  return unique.join(' y ')
}

const fetchPayments = async () => {
  loadingPayments.value = true
  try {
    const res = await payStore.getPaymentsForCredit()
    payments.value = res.data || []
  } catch {
    payments.value = []
  } finally {
    loadingPayments.value = false
  }
}

const onPaymentSelected = (val) => {
  creditAmount.value = ''
  description.value = ''
}

const submit = async () => {
  if (!selectedPayment.value) {
    Notify.create({ color: 'negative', message: 'Selecciona un pago' })
    return
  }
  const amount = Number(creditAmount.value)
  if (!amount || amount <= 0) {
    Notify.create({ color: 'negative', message: 'Ingresa un monto válido' })
    return
  }
  if (amount > remainingAvailable.value) {
    Notify.create({ color: 'negative', message: `El monto no puede exceder S/. ${remainingAvailable.value.toFixed(2)} disponible` })
    return
  }
  if (!applicableMonth.value || !applicableYear.value) {
    Notify.create({ color: 'negative', message: 'Selecciona el mes y año de aplicación' })
    return
  }

  loading.value = true
  try {
    const res = await payStore.storeManualCredit({
      pay_id: selectedPayment.value.id,
      amount,
      description: description.value || null,
      applicable_month: Number(applicableMonth.value),
      applicable_year: Number(applicableYear.value),
    })
    const months = formatAssignedMonths(res?.assigned)
    Notify.create({
      color: 'positive',
      message: months
        ? `Saldo creado — aplicable en ${months}`
        : 'Saldo a favor creado correctamente',
    })
    emit('created')
    show.value = false
    resetForm()
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al crear saldo a favor' })
  } finally {
    loading.value = false
  }
}

const resetForm = () => {
  selectedPayment.value = null
  creditAmount.value = ''
  description.value = ''
  applicableMonth.value = new Date().getMonth() + 1
  applicableYear.value = new Date().getFullYear()
}

watch(show, (val) => {
  if (val) {
    fetchPayments()
    resetForm()
  }
})
</script>

<template>
  <q-dialog v-model="show" persistent>
    <q-card class="w-full" style="max-width: 480px; border-radius: 1rem;">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6 text-bold text-grey-9">Crear saldo a favor</div>
        <q-space />
        <q-btn icon="eva-close-outline" flat round dense @click="show = false" />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <div class="text-caption text-grey-6 mb-3">
          Selecciona un pago de cuota (completado o pendiente), define cuánto del monto es saldo a favor
          y el mes/año en que se aplicará. El saldo solo se descuenta en la cuota de ese mes.
        </div>

        <!-- Select de pagos -->
        <q-select
          v-model="selectedPayment"
          :options="payments"
          :option-label="paymentOptionLabel"
          :option-value="(opt) => opt"
          emit-value
          map-options
          outlined
          dense
          use-input
          input-debounce="300"
          :loading="loadingPayments"
          placeholder="Buscar pago..."
          class="q-mb-sm"
          color="teal"
          @update:model-value="onPaymentSelected"
        >
          <template v-slot:option="scope">
            <q-item v-bind="scope.itemProps">
              <q-item-section>
                <q-item-label class="text-bold">{{ scope.opt.user_name }} - DPT {{ scope.opt.departament_numbers }}</q-item-label>
                <q-item-label caption>
                  S/. {{ Number(scope.opt.amount).toFixed(2) }} | Ref: {{ scope.opt.reference }} | {{ scope.opt.status === 3 ? 'Completado' : 'Pendiente' }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </template>

          <template v-slot:no-option>
            <q-item>
              <q-item-section class="text-grey-5">No hay pagos disponibles</q-item-section>
            </q-item>
          </template>
        </q-select>

        <!-- Monto pagado -->
        <q-input
          v-model="creditAmount"
          type="number"
          outlined
          dense
          label="Monto pagado (S/.)"
          :disable="!selectedPayment"
          :max="remainingAvailable"
          min="0.01"
          step="0.01"
          color="teal"
          class="q-mb-sm"
          :rules="[val => !!val && Number(val) > 0 || 'Ingresa un monto válido']"
        />

        <!-- Descripción -->
        <q-input
          v-model="description"
          outlined
          dense
          label="Descripción (opcional)"
          :disable="!selectedPayment"
          color="teal"
          maxlength="500"
        />

        <!-- Mes de aplicación -->
        <div class="row q-col-gutter-sm q-mt-xs">
          <div class="col-7">
            <q-select
              v-model="applicableMonth"
              :options="monthOptions"
              emit-value
              map-options
              outlined
              dense
              label="Mes de aplicación"
              color="teal"
              :disable="!selectedPayment"
              class="q-mb-sm"
            />
          </div>
          <div class="col-5">
            <q-select
              v-model="applicableYear"
              :options="yearOptions"
              emit-value
              map-options
              outlined
              dense
              label="Año"
              color="teal"
              :disable="!selectedPayment"
              class="q-mb-sm"
            />
          </div>
        </div>

        <!-- Resumen -->
        <div v-if="selectedPayment && creditAmount && Number(creditAmount) > 0" class="bg-green-50 rounded-lg p-3 mt-3">
          <div class="text-caption text-grey-7">
            <div class="flex justify-between">
              <span>Monto del pago:</span>
              <span>S/. {{ maxAmount.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between mt-1">
              <span>Monto pagado:</span>
              <span>S/. {{ Number(creditAmount).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between mt-1" style="border-top: 1px solid #a5d6a7; padding-top: 4px;">
              <span class="text-bold">Saldo a favor a crear:</span>
            </div>
            <q-input
              :model-value="Number(creditAmount).toFixed(2)"
              readonly
              disable
              dense
              outlined
              class="q-mt-xs text-bold"
              color="green"
            />
          </div>
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat label="Cancelar" color="grey-7" @click="show = false" />
        <q-btn
          unelevated
          label="Crear saldo"
          color="primary"
          :loading="loading"
          :disable="!selectedPayment || !creditAmount || Number(creditAmount) <= 0"
          @click="submit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>
