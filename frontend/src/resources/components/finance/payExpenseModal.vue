<script setup>
import { ref, watch } from 'vue'
import { useExpenseStore } from '@/services/store/expense.store'
import { useFinancialAccountStore } from '@/services/store/financialAccount.store'
import { useTransactionCategoryStore } from '@/services/store/transactionCategory.store'
import createFinancialAccountModal from '@/components/finance/createFinancialAccountModal.vue'
import createTransactionCategoryModal from '@/components/finance/createTransactionCategoryModal.vue'
import moment from 'moment'

const expenseStore = useExpenseStore()
const financialAccountStore = useFinancialAccountStore()
const transactionCategoryStore = useTransactionCategoryStore()

const props = defineProps({
  dialog: {
    type: Boolean,
    default: false,
  },
  expense: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['closeModal', 'paid'])

const dialogVisible = ref(props.dialog)
const loadingOptions = ref(false)
const payLoading = ref(false)
const payForm = ref({
  amount: 0,
  financial_account_id: null,
  transaction_category_id: null,
  reference: '',
  pay_date: moment().format('DD/MM/YYYY'),
  vaucher: null,
})
const payFormErrors = ref({})
const financialAccounts = ref([])
const filteredFinancialAccounts = ref([])
const transactionCategories = ref([])

const createAccountDialog = ref(false)
const createCategoryDialog = ref(false)

const resetForm = () => {
  payForm.value = {
    amount: props.expense?.amount || 0,
    financial_account_id: null,
    transaction_category_id: null,
    reference: '',
    pay_date: moment().format('DD/MM/YYYY'),
    vaucher: null,
  }
  payFormErrors.value = {}
}

const loadOptions = async () => {
  loadingOptions.value = true
  try {
    const [accountsRes, categoriesRes] = await Promise.all([
      financialAccountStore.getFinancialAccounts(),
      transactionCategoryStore.getTransactionCategories(2),
    ])
    financialAccounts.value = accountsRes.data || []
    filteredFinancialAccounts.value = financialAccounts.value
    transactionCategories.value = categoriesRes.data || []
  } catch (e) {
    console.error('Error loading form options', e)
  } finally {
    loadingOptions.value = false
  }
}

const onAccountCreated = (created) => {
  if (!created?.id) return
  const exists = financialAccounts.value.some((a) => a.id === created.id)
  if (!exists) {
    financialAccounts.value = [...financialAccounts.value, created]
  }
  filteredFinancialAccounts.value = financialAccounts.value
  payForm.value.financial_account_id = created.id
}

const filterFinancialAccounts = (val, update) => {
  update(() => {
    const needle = (val || '').toLowerCase().trim()
    if (!needle) {
      filteredFinancialAccounts.value = financialAccounts.value
      return
    }
    filteredFinancialAccounts.value = financialAccounts.value.filter((a) =>
      a.name?.toLowerCase().includes(needle)
    )
  })
}

const onFilterAbort = () => {
  filteredFinancialAccounts.value = financialAccounts.value
}

const onCategoryCreated = (created) => {
  if (!created?.id) return
  const exists = transactionCategories.value.some((c) => c.id === created.id)
  if (!exists) {
    transactionCategories.value = [...transactionCategories.value, created]
  }
  payForm.value.transaction_category_id = created.id
}

const optionsFn = (date) => {
  return date <= moment().format('YYYY/MM/DD')
}

const close = () => {
  emit('closeModal')
}

const submitPay = async () => {
  payFormErrors.value = {}

  if (!payForm.value.financial_account_id) {
    payFormErrors.value.financial_account_id = 'Seleccione una cuenta financiera'
    return
  }
  if (!payForm.value.transaction_category_id) {
    payFormErrors.value.transaction_category_id = 'Seleccione una categoría'
    return
  }
  if (!payForm.value.pay_date) {
    payFormErrors.value.pay_date = 'Seleccione una fecha de pago'
    return
  }
  if (moment(payForm.value.pay_date, 'DD/MM/YYYY').isAfter(moment(), 'day')) {
    payFormErrors.value.pay_date = 'La fecha de pago no puede ser mayor a la actual'
    return
  }

  payLoading.value = true
  try {
    const fd = new FormData()
    fd.append('amount', payForm.value.amount)
    fd.append('financial_account_id', payForm.value.financial_account_id)
    fd.append('transaction_category_id', payForm.value.transaction_category_id)
    fd.append('reference', payForm.value.reference || '')
    fd.append('pay_date', moment(payForm.value.pay_date).format('YYYY-DD-MM'))
    if (payForm.value.vaucher) {
      fd.append('vaucher', payForm.value.vaucher)
    }

    await expenseStore.payExpense(props.expense?.id, fd)
    emit('paid')
    close()
  } catch (e) {
    payFormErrors.value = { general: e || 'Error al registrar el pago' }
  } finally {
    payLoading.value = false
  }
}

watch(
  () => props.dialog,
  (open) => {
    dialogVisible.value = open
    if (open) {
      resetForm()
      loadOptions()
    }
  }
)

watch(dialogVisible, (open) => {
  if (!open && props.dialog) {
    close()
  }
})
</script>

<template>
  <q-dialog v-model="dialogVisible" persistent @hide="close">
    <q-card class="q-py-md q-px-lg" style="min-width: 500px; max-width: 60px;">
      <div class="text-h6 text-bold q-mb-md">REGISTRAR PAGO DE GASTO</div>

      <div v-if="payFormErrors.general" class="bg-red-1 text-red-7 q-pa-sm rounded q-mb-md text-body2">
        {{ payFormErrors.general }}
      </div>

      <div class="text-caption text-grey-6 q-mb-xs">Proveedor</div>
      <div class="text-body1 text-bold q-mb-md">{{ expense?.provider?.name || '—' }}</div>

      <div class="text-caption text-grey-6 q-mb-xs">Monto</div>
      <q-input v-model="payForm.amount" type="number" prefix="S/" dense outlined class="q-mb-md" />

      <div class="text-caption text-grey-6 q-mb-xs">Cuenta financiera *</div>
      <div class="row items-center">
        <div class="col-11">
          <q-select
            v-model="payForm.financial_account_id"
            :options="filteredFinancialAccounts"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            dense outlined
            use-input
            input-debounce="0"
            @filter="filterFinancialAccounts"
            @filter-abort="onFilterAbort"
            @popup-hide="onFilterAbort"
            :loading="loadingOptions"
            :error="!!payFormErrors.financial_account_id"
            :error-message="payFormErrors.financial_account_id"
          >
            <template v-slot:no-option>
              <q-item>
                <q-item-section class="text-grey">
                  No se encontraron cuentas
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>
        <div class="col-1 flex flex-center pb-5">
          <q-btn flat dense round color="primary" @click="createAccountDialog = true">
            <q-icon name="eva-plus-outline" />
            <q-tooltip>Nueva cuenta financiera</q-tooltip>
          </q-btn>
        </div>
      </div>

      <div class="text-caption text-grey-6 q-mb-xs">Categoría de transacción *</div>
      <div class="row items-center">
        <div class="col-11">
          <q-select
            v-model="payForm.transaction_category_id"
            :options="transactionCategories"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            dense outlined
            :loading="loadingOptions"
            :error="!!payFormErrors.transaction_category_id"
            :error-message="payFormErrors.transaction_category_id"
          />
        </div>
        <div class="col-1 flex flex-center pb-5">
          <q-btn flat dense round color="primary" @click="createCategoryDialog = true">
            <q-icon name="eva-plus-outline" />
            <q-tooltip>Nueva categoría contable</q-tooltip>
          </q-btn>
        </div>
      </div>

      <div class="text-caption text-grey-6 q-mb-xs">Referencia</div>
      <q-input v-model="payForm.reference" dense outlined class="q-mb-md" />

      <div class="text-caption text-grey-6 q-mb-xs">Fecha de pago *</div>
      <q-input
        v-model="payForm.pay_date"
        dense
        outlined
        class="q-mb-md"
        :error="!!payFormErrors.pay_date"
        :error-message="payFormErrors.pay_date"
      >
        <template v-slot:append>
          <q-icon name="eva-calendar-outline" class="cursor-pointer">
            <q-popup-proxy cover transition-show="scale" transition-hide="scale">
              <q-date
                v-model="payForm.pay_date"
                mask="DD/MM/YYYY"
                :options="optionsFn"
                :navigation-max-year-month="moment().format('YYYY/MM')"
              />
            </q-popup-proxy>
          </q-icon>
        </template>
      </q-input>

      <div class="text-caption text-grey-6 q-mb-xs">Vaucher de pago (opcional)</div>
      <q-file
        v-model="payForm.vaucher"
        dense outlined
        accept=".jpg,.jpeg,.png,.webp,.pdf"
        max-file-size="10485760"
        class="q-mb-md"
      >
        <template v-slot:prepend>
          <q-icon name="eva-attach-outline" />
        </template>
      </q-file>

      <div class="row justify-end q-gutter-sm q-mt-md">
        <q-btn flat label="Cancelar" color="grey-7" no-caps @click="close" :disable="payLoading" />
        <q-btn
          label="Registrar pago"
          color="positive"
          no-caps
          :loading="payLoading"
          @click="submitPay"
        />
      </div>

      <createFinancialAccountModal
        :dialog="createAccountDialog"
        @close-modal="createAccountDialog = false"
        @created="onAccountCreated"
      />

      <createTransactionCategoryModal
        :dialog="createCategoryDialog"
        :default-type="2"
        @close-modal="createCategoryDialog = false"
        @created="onCategoryCreated"
      />
    </q-card>
  </q-dialog>
</template>
