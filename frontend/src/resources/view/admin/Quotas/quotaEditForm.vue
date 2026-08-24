<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'

const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()

const loading = ref(false)
const loadingData = ref(false)

const parseMaskedMoney = (value) => {
  if (value === null || value === undefined) return null
  const raw = String(value).trim()
  if (!raw) return null
  const normalized = raw.replaceAll('.', '').replace(',', '.')
  const n = Number.parseFloat(normalized)
  if (!Number.isFinite(n)) return null
  return Number(n.toFixed(2))
}

const formatMaskedMoney = (value) => {
  if (value === null || value === undefined) return ''
  const n = typeof value === 'number' ? value : Number(value)
  if (!Number.isFinite(n)) return ''
  const fixed = n.toFixed(2)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const statusOptions = [
  { label: 'Cancelada', value: 0 },
  { label: 'Pago pendiente', value: 1 },
  { label: 'Pendiente de aprobacion', value: 2 },
  { label: 'Pagado', value: 3 },
]

const quotaId = computed(() => route.params.id)

const formData = ref({
  maintenance_amount: '',
  water_amount: '',
  amount: '',
  status: null,
  due_date: '',
  description: '',
})

const computedTotal = computed(() => {
  const maint = parseMaskedMoney(formData.value.maintenance_amount) ?? 0
  const water = parseMaskedMoney(formData.value.water_amount) ?? 0
  return formatMaskedMoney(maint + water)
})

watch(
  () => [formData.value.maintenance_amount, formData.value.water_amount],
  () => {
    formData.value.amount = computedTotal.value
  }
)

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000,
  })
}

const loadQuota = async () => {
  loadingData.value = true
  try {
    const response = await quotaStore.getQuotaById(quotaId.value)
    const q = response.data

    formData.value.maintenance_amount = formatMaskedMoney(q.maintenance_amount)
    formData.value.water_amount = formatMaskedMoney(q.water_amount)
    formData.value.amount = formatMaskedMoney(q.amount)
    formData.value.status = statusOptions.find((s) => s.value === Number(q.status)) ?? null
    formData.value.due_date = q.due_date || ''
    formData.value.description = q.description || ''
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo cargar la cuota')
  } finally {
    loadingData.value = false
  }
}

const submit = async () => {
  loading.value = true
  try {
    const payload = {
      id: quotaId.value,
      maintenance_amount: parseMaskedMoney(formData.value.maintenance_amount),
      water_amount: parseMaskedMoney(formData.value.water_amount),
      amount: parseMaskedMoney(formData.value.amount),
      status: formData.value.status?.value,
      due_date: formData.value.due_date || null,
      description: formData.value.description || null,
    }

    const response = await quotaStore.updateQuota(payload)
    if (response?.code !== 200) throw response

    showNotify('positive', 'Cuota actualizada con exito')
    router.go(-1)
  } catch (err) {
    showNotify('negative', err?.error || err?.message || 'No se pudo actualizar la cuota')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadQuota()
})
</script>

<template>
  <div class="md:px-20 px-2 pb-8 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Editar cuota
    </div>

    <div v-if="loadingData" class="flex justify-center items-center py-20">
      <q-spinner-dots color="primary" size="7rem" />
    </div>

    <q-form v-else @submit="submit()">
      <div class="row w-full">
        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto mantenimiento (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.maintenance_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto agua (S/.)</div>
          <q-input dense borderless clearable class="form__inputsR mt-1" color="primary"
            v-model="formData.water_amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Monto total (S/.)</div>
          <q-input dense borderless class="form__inputsR mt-1" color="primary"
            v-model="formData.amount" mask="###.###.###,##" reverse-fill-mask inputmode="decimal" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Estado</div>
          <q-select dense borderless class="form__inputsR mt-1" v-model="formData.status"
            :options="statusOptions" option-label="label" option-value="value"
            :rules="[val => !!val || 'El estado es requerido']" />
        </div>

        <div class="col-md-6 col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Fecha de vencimiento</div>
          <q-input dense borderless class="form__inputsR mt-1" color="primary" type="date"
            v-model="formData.due_date" />
        </div>

        <div class="col-12 mt-2 px-2 md:px-12">
          <div class="text-subtitle2 text-black">Descripcion</div>
          <q-input dense borderless class="form__inputsR mt-1" color="primary"
            v-model="formData.description" type="textarea" autogrow />
        </div>

        <div class="col-12 mb-2 px-2 md:px-12 flex justify-end mt-4">
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar cambios</div>
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
