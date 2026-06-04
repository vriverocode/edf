<script setup>
import { computed, ref, watch } from 'vue'
import { Notify } from 'quasar'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useRouter } from 'vue-router'


const loading = ref(false)
const monthlyBillsStore = useMonthlyBillsStore()
const router = useRouter();
const parseMaskedMoney = (value) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(2))
}

const formatMaskedMoney = (value, decimals = 2) => {
  if (value === null || value === undefined) return ''
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return ''
  const fixed = n.toFixed(decimals)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const now = new Date()
const monthOptions = [
  { value: 1, name: 'Enero' },
  { value: 2, name: 'Febrero' },
  { value: 3, name: 'Marzo' },
  { value: 4, name: 'Abril' },
  { value: 5, name: 'Mayo' },
  { value: 6, name: 'Junio' },
  { value: 7, name: 'Julio' },
  { value: 8, name: 'Agosto' },
  { value: 9, name: 'Septiembre' },
  { value: 10, name: 'Octubre' },
  { value: 11, name: 'Noviembre' },
  { value: 12, name: 'Diciembre' }
]

const formData = ref({
  month: monthOptions[now.getMonth() - 1],
  year: now.getFullYear(),
  total_maintenance_budget: '',
  total_water_bill_amount: '',
  total_water_consumption_m3: null,
  water_price_per_m3: ''
})

const hasWaterTotals = computed(() => {
  const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
  const consumption = Number(formData.value.total_water_consumption_m3)
  return amount !== null && amount > 0 && Number.isFinite(consumption) && consumption > 0
})

const waterPriceReadonly = computed(() => hasWaterTotals.value)

watch(
  () => [formData.value.total_water_bill_amount, formData.value.total_water_consumption_m3],
  () => {
    if (!hasWaterTotals.value) return
    const amount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const consumption = Number(formData.value.total_water_consumption_m3)
    if (amount === null || !Number.isFinite(consumption) || consumption <= 0) return
    const computedPrice = amount / consumption

    formData.value.water_price_per_m3 = Number.isFinite(computedPrice) ? formatMaskedMoney(computedPrice, 4) : ''
  }
)

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const submit = async () => {
  loading.value = true
  try {
    const totalMaintenanceBudget = parseMaskedMoney(formData.value.total_maintenance_budget)
    const totalWaterBillAmount = parseMaskedMoney(formData.value.total_water_bill_amount)
    const waterPricePerM3 = parseMaskedMoney(formData.value.water_price_per_m3)
    const payload = {
      month: formData.value.month?.value,
      year: Number(formData.value.year),
      total_maintenance_budget: totalMaintenanceBudget,
      total_water_bill_amount: totalWaterBillAmount,
      total_water_consumption_m3: formData.value.total_water_consumption_m3 === null || formData.value.total_water_consumption_m3 === '' ? null : Number(formData.value.total_water_consumption_m3),
      water_price_per_m3: waterPricePerM3
    }

    const response = await monthlyBillsStore.createMonthlyBill(payload)
    if (response?.code !== 200) throw response

    showNotify('positive', 'Presupuesto mensual registrado con éxito')
    setTimeout(() => router.go(-1), 1000)
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo registrar el presupuesto mensual'
    showNotify('negative', apiError)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="md:px-20 px-2  pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold  my-2">
      Presupuesto mensual
    </div>

    <q-form @submit="submit()">
      <div class="row w-full">
        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Mes</div>
          <q-select dense borderless class="form__inputsR mt-1" v-model="formData.month" :options="monthOptions"
            option-label="name" option-value="value" :rules="[val => !!val || 'El mes es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Año</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" type="number"
            v-model.number="formData.year" :rules="[val => !!val || 'El año es requerido']" />
        </div>

        <div class="col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Presupuesto total a distribuir (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_maintenance_budget" mask="###.###.###,##" reverse-fill-mask inputmode="decimal"
            :rules="[
              val => parseMaskedMoney(val) !== null || 'El presupuesto total es requerido'
            ]" />
        </div>

        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto total recibo de agua (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.total_water_bill_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-4 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Consumo total de agua (m³)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" mask="###.###.###,####"
            reverse-fill-mask inputmode="decimal" v-model.number="formData.total_water_consumption_m3" />
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Costo unitario de agua por m³ (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary" mask="###.###.###,####"
            reverse-fill-mask inputmode="decimal" :rules="[
              val => parseMaskedMoney(val) !== null || 'El costo unitario de agua es requerido'
            ]" v-model="formData.water_price_per_m3" :readonly="waterPriceReadonly"
            :hint="waterPriceReadonly ? 'Calculado automáticamente (Monto / Consumo)' : 'Ingresa el costo unitario si no registras los totales'" />
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar</div>
          </q-btn>
        </div>
      </div>
    </q-form>
  </div>
</template>

<style lang="scss">
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
