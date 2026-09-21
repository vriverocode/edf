<script setup>
import { ref, watch, computed } from 'vue'
import { usePayStore } from '@/services/store/pay.store'
import { Notify } from 'quasar'

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
  return `S/. ${Number(pay.amount).toFixed(2)} | Ref: ${pay.reference} | ${statusText}`
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

  loading.value = true
  try {
    await payStore.storeManualCredit({
      pay_id: selectedPayment.value.id,
      amount,
      description: description.value || null,
    })
    Notify.create({ color: 'positive', message: 'Saldo a favor creado correctamente' })
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
          Selecciona un pago de cuota (completado o pendiente) y define cuánto del monto es saldo a favor.
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

        <!-- Info del pago seleccionado -->
        <div v-if="selectedPayment" class="bg-blue-50 rounded-lg p-3 q-mb-sm">
          <div class="text-caption text-grey-7">
            <div class="flex justify-between">
              <span>Monto del pago:</span>
              <span class="text-bold">S/. {{ maxAmount.toFixed(2) }}</span>
            </div>
            <div class="flex justify-between mt-1">
              <span>Ya aplicado como crédito:</span>
              <span class="text-orange-7">S/. {{ Number(selectedPayment.credit_applied || 0).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between mt-1" style="border-top: 1px dashed #90caf9; padding-top: 4px;">
              <span class="text-bold">Disponible para crédito:</span>
              <span class="text-bold text-green-7">S/. {{ remainingAvailable.toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <!-- Monto del crédito -->
        <q-input
          v-model="creditAmount"
          type="number"
          outlined
          dense
          label="Saldo a favor (S/.)"
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
