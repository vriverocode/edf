<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Notify, date } from 'quasar'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useReserveStore } from '@/services/store/reserve.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { usePayStore } from '@/services/store/pay.store'
import { usePayMethodStore } from '@/services/store/payMethod.store'
import { useUserStore } from '@/services/store/users.store'

const router = useRouter()
const apartmentStore = useApartmentStore()
const reserveStore = useReserveStore()
const quotaStore = useQuotaStore()
const payStore = usePayStore()
const payMethodStore = usePayMethodStore()
const userStore = useUserStore()

const loading = ref(false)
const submitting = ref(false)

// Step 1: Type
const selectedType = ref(null)
const typeOptions = [
  { label: 'Cuota de mantenimiento', value: 1 },
  { label: 'Reserva de área común', value: 2 },
]

// Step 1a: User selector (for type=1)
const selectedUserId = ref(null)
const userOptions = ref([])
const userSearch = ref('')

// Step 1b: Department selector (type=2 only; type=1 uses chips from selectedUser.units)
const selectedDept = ref(null)
const departments = ref([])

// Derived user from selected department (for type=2)
const selectedUser = computed(() => {
  if (isQuotaType.value) {
    return userOptions.value.find(u => u.id === selectedUserId.value) || null
  }
  return selectedDept.value?.owner || null
})

const userUnits = computed(() => selectedUser.value?.units || [])

const unitTypeShort = (type) => {
  const map = { 1: 'DPT', 2: 'EST', 3: 'DPO', 4: 'LAV' }
  return map[Number(type)] || 'UNI'
}

const unitChipLabel = (u) => {
  const base = `${u.number}`
  return u.block ? `${base} · B${u.block}` : base
}

const unitMatch = (unit, q) => {
  if (!q) return false
  const inter = String(unit.inter_number ?? '')
  const number = String(unit.number ?? '')
  const block = String(unit.block ?? '')
  return (
    inter.includes(q) ||
    number.toLowerCase().includes(q) ||
    block.toLowerCase().includes(q)
  )
}

const monthLabel = computed(() => {
  if (!selectedMonth.value) return 'Todos los meses'
  const opt = monthOptions.find(m => m.value === selectedMonth.value)
  if (!opt) return ''
  const year = selectedYear.value || currentYear
  const label = opt.label.charAt(0).toUpperCase() + opt.label.slice(1)
  return `${label} ${year}`
})

const reserveDeptSearch = ref('')

const filteredDepts = computed(() => {
  if (!reserveDeptSearch.value) return departments.value
  const q = reserveDeptSearch.value.toLowerCase()
  return departments.value.filter(
    d => String(d.number).toLowerCase().includes(q) || d.block?.toLowerCase().includes(q),
  )
})

// Step 2a: Month/Year for quotas (opcionales: sin filtro se ven todos los
// meses agrupados, que es el flujo habitual de "registrar pago multi-mes")
const selectedMonth = ref(null)
const selectedYear = ref(null)
const currentYear = new Date().getFullYear()
const monthOptions = Array.from({ length: 12 }, (_, i) => ({
  label: new Date(2000, i).toLocaleString('es-Ve', { month: 'long' }).toLocaleUpperCase(),
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
const selectedQuotas = ref([])

// Step 3: Payment form
const paymentForm = ref({
  amount: null,
  pay_method: null,
  pay_date: date.formatDate(new Date(), 'YYYY-MM-DD'),
  reference: '',
  vaucher: null,
})

const payMethods = ref([])
const creditBalance = ref(0)

const isQuotaType = computed(() => Number(selectedType.value) === 1)
const isReserveType = computed(() => Number(selectedType.value) === 2)

const quotaPeriodOf = (q) => {
  const month = Number(q.month) || 0
  let year = currentYear
  if (q.year != null && q.year !== '') year = Number(q.year)
  else if (q.due_date) year = new Date(q.due_date).getFullYear()
  return { month, year, key: year * 100 + month }
}

// Saldo a favor: cada crédito solo aplica a cuotas de su propio mes/año,
// con tope por depto y periodo (mismo criterio que CreditService en el
// backend). Así un pago de julio+junio+octubre usa también el crédito de junio.
const round2 = (n) => Math.round(n * 100) / 100

const selectedPeriods = computed(() => {
  const map = new Map()
  selectedQuotas.value.forEach((q) => {
    const p = quotaPeriodOf(q)
    if (p.month >= 1 && p.month <= 12 && !map.has(p.key)) map.set(p.key, p)
  })
  return [...map.values()].sort((a, b) => a.key - b.key)
})

// { [periodKey]: { total, detail: { deptId: saldo } } }
const creditByPeriod = ref({})

// Por periodo y depto: min(saldo del depto en ese periodo, cuotas del
// depto en ese periodo). Espejo exacto de CreditService::maxApplicableForQuotas.
const creditAllocations = computed(() => {
  const rows = []
  if (!isQuotaType.value) return rows
  for (const p of selectedPeriods.value) {
    const info = creditByPeriod.value[p.key]
    if (!info) continue
    const byDept = {}
    selectedQuotas.value.forEach((q) => {
      if (quotaPeriodOf(q).key !== p.key) return
      const id = q.departament_id
      if (!id) return
      byDept[id] = (byDept[id] || 0) + (Number(q.amount) || 0)
    })
    let applied = 0
    for (const [deptId, amount] of Object.entries(byDept)) {
      applied += Math.min(Number(info.detail[deptId]) || 0, amount)
    }
    applied = round2(applied)
    if (applied > 0) {
      rows.push({
        key: p.key,
        label: `${monthOptions[p.month - 1]?.label ?? ''} ${p.year}`,
        applied,
      })
    }
  }
  return rows
})

const creditToApply = computed(() => round2(creditAllocations.value.reduce((sum, r) => sum + r.applied, 0)))

const hasCredit = computed(() => isQuotaType.value && creditToApply.value > 0)

const amountToPay = computed(() => {
  const total = totalSelectedAmount.value
  if (!hasCredit.value) return total
  return Math.max(0, round2(total - creditToApply.value))
})

const fetchCreditBalance = async () => {
  creditBalance.value = 0
  creditByPeriod.value = {}
  if (!isQuotaType.value || !selectedQuotas.value.length) return
  const periods = selectedPeriods.value
  if (!periods.length) return
  const deptIds = [...new Set(selectedQuotas.value.map((q) => q.departament_id).filter(Boolean))]
  if (!deptIds.length) return
  try {
    const results = await Promise.all(
      periods.map((p) => payStore.getCreditBalanceForDepartments(deptIds, p.month, p.year)),
    )
    const byPeriod = {}
    let total = 0
    results.forEach((res, i) => {
      if (res?.code !== 200) return
      byPeriod[periods[i].key] = { total: res.data.total || 0, detail: res.data.detail || {} }
      total += res.data.total || 0
    })
    creditByPeriod.value = byPeriod
    creditBalance.value = round2(total)
  } catch {
    creditByPeriod.value = {}
    creditBalance.value = 0
  }
}

const selectedPayMethodCommission = computed(() => {
  if (!paymentForm.value.pay_method) return 0
  const method = payMethods.value.find(m => m.id === paymentForm.value.pay_method)
  return method?.commission_percentage || 0
})

const commissionAmount = computed(() => {
  if (!selectedPayMethodCommission.value || !paymentForm.value.amount) return 0
  return Number((Math.ceil((paymentForm.value.amount * selectedPayMethodCommission.value / 100) * 100) / 100).toFixed(2))
})

const netAmount = computed(() => {
  if (!paymentForm.value.amount) return 0
  return Number((Math.ceil((paymentForm.value.amount - commissionAmount.value) * 100) / 100).toFixed(2))
})

const filteredUsers = computed(() => {
  if (!userSearch.value) return userOptions.value
  const q = userSearch.value.trim().toLowerCase()
  if (!q) return userOptions.value
  const digits = q.replace(/\D/g, '')
  return userOptions.value.filter((u) => {
    if (u.name?.toLowerCase().includes(q)) return true
    const units = u.units || []
    if (digits && units.some((unit) => unitMatch(unit, digits))) return true
    return units.some((unit) => unitMatch(unit, q))
  })
})

const loadUsers = async () => {
  try {
    const res = await userStore.getUsersOptions()
    userOptions.value = res.data || []
  } catch {
    userOptions.value = []
  }
}

const loadDepartmentsByUser = async (userId) => {
  if (!userId) { departments.value = []; return }
  try {
    const res = await apartmentStore.getDepartmentsByOwner(userId)
    departments.value = res.data || []
  } catch {
    departments.value = []
  }
}

const loadAllDepartments = async () => {
  try {
    const res = await apartmentStore.getApartmentsByFind('allWithUser')
    departments.value = res.data || []
  } catch {
    departments.value = []
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
  if (!selectedUser.value) return
  pendingQuotas.value = []
  selectedItem.value = null
  selectedQuotas.value = []
  try {
    if (selectedMonth.value) {
      // Flujo filtrado por mes (opcional)
      const res = await quotaStore.getQuotaByMonth(selectedMonth.value, {
        year: selectedYear.value || currentYear,
        owner: selectedUser.value.id,
        status: 1,
      })
      pendingQuotas.value = res.data || []
    } else {
      // Sin filtro: todas las cuotas pendientes del usuario, agrupadas por mes
      // en backend y aplanadas aqui para el checkbox-list (cada fila ya
      // trae month_label).
      const res = await quotaStore.getQuotasPendingByOwner(selectedUser.value.id)
      pendingQuotas.value = (res.data || []).flatMap(group => group.details || [])
    }
    selectedQuotas.value = [...pendingQuotas.value]
  } catch {
    pendingQuotas.value = []
    selectedQuotas.value = []
  }
}

const loadPendingBookings = async () => {
  if (!selectedDept.value) return
  pendingBookings.value = []
  selectedItem.value = null
  try {
    const res = await reserveStore.getReservesByUser({
      status: 1,
      department_id: selectedDept.value.id,
    })
    pendingBookings.value = res.data.data || []
  } catch {
    pendingBookings.value = []
  }
}

const totalSelectedAmount = computed(() => {
  if (isQuotaType.value) {
    return selectedQuotas.value.reduce((sum, q) => sum + (Number(q.amount) || 0), 0)
  }
  return selectedItem.value ? Number(selectedItem.value.amount) || 0 : 0
})

// Watches
watch(selectedType, (val) => {
  selectedItem.value = null
  selectedQuotas.value = []
  selectedDept.value = null
  selectedUserId.value = null
  departments.value = []
  pendingQuotas.value = []
  pendingBookings.value = []
  creditBalance.value = 0
  if (Number(val) === 1) {
    loadUsers()
  } else if (Number(val) === 2) {
    loadAllDepartments()
  }
})

watch(selectedUserId, (id) => {
  departments.value = []
  pendingQuotas.value = []
  selectedItem.value = null
  selectedQuotas.value = []
  creditBalance.value = 0
  if (!id) return
  if (isReserveType.value) loadDepartmentsByUser(id)
  if (isQuotaType.value) loadPendingQuotas()
})

watch([selectedMonth, selectedYear], () => {
  if (isQuotaType.value && selectedUser.value) loadPendingQuotas()
})

watch(selectedDept, () => {
  selectedItem.value = null
  if (isReserveType.value && selectedDept.value) loadPendingBookings()
})

watch(selectedItem, (item) => {
  if (item && !isQuotaType.value) {
    paymentForm.value.amount = Number(item.amount) || 0
  } else if (!isQuotaType.value) {
    paymentForm.value.amount = null
  }
})

watch(selectedQuotas, (items) => {
  if (isQuotaType.value && items.length) {
    fetchCreditBalance().then(() => {
      paymentForm.value.amount = amountToPay.value
    })
  } else {
    if (isQuotaType.value) paymentForm.value.amount = null
    creditBalance.value = 0
  }
}, { deep: true })

const toggleQuota = (quota) => {
  const idx = selectedQuotas.value.findIndex(q => q.id === quota.id)
  if (idx >= 0) {
    selectedQuotas.value.splice(idx, 1)
  } else {
    selectedQuotas.value.push(quota)
  }
}

const isQuotaSelected = (quota) => selectedQuotas.value.some(q => q.id === quota.id)

const allQuotasSelected = computed(
  () => pendingQuotas.value.length > 0 && selectedQuotas.value.length === pendingQuotas.value.length,
)

const toggleAllQuotas = () => {
  if (allQuotasSelected.value) {
    selectedQuotas.value = []
  } else {
    selectedQuotas.value = [...pendingQuotas.value]
  }
}

const selectedUnitsCount = computed(
  () => new Set(selectedQuotas.value.map((q) => q.departament_id).filter(Boolean)).size,
)

// Render agrupado por mes para el listado multi-mes: cada fila conserva su
// checkbox individual pero se ordena bajo el encabezado de su periodo.
const pendingQuotaGroups = computed(() => {
  const groups = new Map()
  pendingQuotas.value.forEach((quota) => {
    const p = quotaPeriodOf(quota)
    if (!groups.has(p.key)) {
      const label = Number(p.month) >= 1 && Number(p.month) <= 12
        ? `${monthOptions[p.month - 1]?.label ?? ''} ${p.year}`
        : `Cuota Periodo ${p.year}`
      groups.set(p.key, { key: p.key, label, quotas: [] })
    }
    groups.get(p.key).quotas.push(quota)
  })
  return [...groups.values()].sort((a, b) => b.key - a.key)
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
  if (!selectedUser.value || !selectedType.value) {
    Notify.create({ color: 'negative', message: 'Completa todos los campos requeridos' })
    return
  }

  if (isQuotaType.value) {
    if (!selectedQuotas.value.length) {
      Notify.create({ color: 'negative', message: 'Selecciona al menos una cuota' })
      return
    }
  } else {
    if (!selectedItem.value) {
      Notify.create({ color: 'negative', message: 'Selecciona una reserva' })
      return
    }
  }

  const amountValue = Number(paymentForm.value.amount)
  const creditCoversAll = hasCredit.value && creditToApply.value >= totalSelectedAmount.value
  if ((!Number.isFinite(amountValue) || amountValue <= 0) && !creditCoversAll) {
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
  formData.append('amount', paymentForm.value.amount)
  formData.append('pay_method', paymentForm.value.pay_method)
  formData.append('pay_date', paymentForm.value.pay_date)
  formData.append('reference', paymentForm.value.reference || '000000')

  if (isQuotaType.value) {
    const ids = selectedQuotas.value.map(q => q.id)
    formData.append('to_pay_id', ids[0])
    ids.forEach(id => formData.append('quota_ids[]', id))
    formData.append('consolidated_ids', JSON.stringify(ids))
  } else {
    formData.append('to_pay_id', selectedItem.value.id)
  }

  if (hasCredit.value && creditToApply.value > 0) {
    formData.append('credit_applied', creditToApply.value)
  }

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

        <!-- Step 1: Type -->
        <div class="mb-6">
          <div class="text-sm font-medium text-gray-700 mb-1">Tipo de pago</div>
          <q-select
            v-model="selectedType"
            :options="typeOptions"
            option-label="label"
            option-value="value"
            emit-value
            borderless
            map-options
            placeholder="Seleccionar tipo"
            clearable
            dense
            class="form__inputsR"
          />
        </div>

        <!-- Step 1a: Cuota type — User + unit chips -->
        <div v-if="isQuotaType" class="space-y-4 mb-6">
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Usuario</div>
            <q-select
              v-model="selectedUserId"
              :options="filteredUsers"
              option-label="name"
              option-value="id"
              emit-value
              map-options
              use-input
              fill-input
              hide-selected
              behavior="menu"
              placeholder="Nombre o n° de unidad (ej. 101)..."
              clearable
              dense
              borderless
              class="form__inputsR"
              @filter="(val, update) => { userSearch = val; update() }"
              @filter-abort="() => { userSearch = '' }"
            >
              <template v-slot:option="{ itemProps, opt }">
                <q-item v-bind="itemProps" dense style="border-bottom: 1px solid lightgrey;" class="my-1 py-1">
                  <q-item-section avatar class="min-w-[36px]">
                    <q-avatar size="28px" color="teal" text-color="white" class="text-xs font-bold">
                      {{ String(opt.name || '?')[0] }}
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-sm font-semibold">{{ opt.name }}</q-item-label>
                    <q-item-label v-if="opt.units?.length" caption class="text-xs">
                      {{ opt.units.map(u => `${unitTypeShort(u.type)} ${u.number}`).join(' · ') }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template v-slot:selected-item="{ opt }">
                <span>
                  {{ opt.name }}
                  <span v-if="opt.units?.length" class="text-grey-7 text-xs">
                    · {{ opt.units[0].number }}<template v-if="opt.units.length > 1"> +{{ opt.units.length - 1 }}</template>
                  </span>
                </span>
              </template>
            </q-select>
          </div>

          <div v-if="userUnits.length">
            <div class="flex items-center justify-between mb-2">
              <div class="text-sm font-medium text-gray-700">Unidades del usuario</div>
              <div class="text-xs text-gray-500">{{ userUnits.length }} unidades (todas en la cuota)</div>
            </div>
            <div class="flex flex-wrap gap-2">
              <q-chip
                v-for="unit in userUnits"
                :key="unit.id"
                dense
                size="sm"
                :color="unit.relation === 'tenant' ? 'amber-3' : 'teal-1'"
                text-color="grey-9"
                class="text-xs"
              >
                <span class="font-semibold">{{ unitChipLabel(unit) }}</span>
                <span class="ml-1 opacity-70">
                  {{ unit.relation === 'tenant' ? 'Inq.' : 'Prop.' }}
                </span>
              </q-chip>
            </div>
          </div>
        </div>

        <!-- Step 1b: Reserva type — Department selector -->
        <div v-if="isReserveType" class="mb-6">
          <div class="text-sm font-medium text-gray-700 mb-1">Departamento / Unidad</div>
          <q-select
            v-model="selectedDept"
            :options="filteredDepts"
            option-label="number"
            option-value="id"
            placeholder="Buscar por número o propietario..."
            use-input
            fill-input
            hide-selected
            behavior="menu"
            clearable
            dense
            borderless
            class="form__inputsR"
            @filter="(val, update) => { reserveDeptSearch = val; update() }"
            @filter-abort="() => { reserveDeptSearch = '' }"
          >
            <template v-slot:option="{ itemProps, opt }">
              <q-item v-bind="itemProps" dense style="border-bottom: 1px solid lightgrey;" class="my-1 py-1">
                <q-item-section avatar class="min-w-[36px]">
                  <q-avatar size="28px" color="teal" text-color="white" class="text-xs font-bold">
                    {{ String(opt.number || '?')[0] }}
                  </q-avatar>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-sm font-semibold">
                    {{ opt.number }}<span v-if="opt.block" class="text-grey-6 font-normal"> · Bloque {{ opt.block }}</span>
                  </q-item-label>
                  <q-item-label caption class="text-xs">{{ opt.owner?.name || 'Sin propietario' }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
            <template v-slot:selected-item="{ opt }">
              <span>{{ opt.number }}<span v-if="opt.block"> · Bloque {{ opt.block }}</span></span>
            </template>
          </q-select>
        </div>

        <!-- Step 2a: Filtros opcionales de mes/año (sin filtro: todos los meses) -->
        <div v-if="isQuotaType" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Mes (opcional)</div>
            <q-select
              v-model="selectedMonth"
              :options="monthOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              borderless
              placeholder="Todos los meses"
              clearable
              dense
              class="form__inputsR"
            />
          </div>
          <div>
            <div class="text-sm font-medium text-gray-700 mb-1">Año (opcional)</div>
            <q-select
              v-model="selectedYear"
              :options="yearOptions"
              option-label="label"
              option-value="value"
              emit-value
              borderless
              map-options
              placeholder="Todos los años"
              clearable
              dense
              class="form__inputsR"
            />
          </div>
        </div>

        <!-- Step 2b: Pending items list — Cuota global -->
        <div v-if="isQuotaType && pendingQuotas.length" class="mb-6">
          <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 mb-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
              <div>
                <div class="text-sm font-semibold text-gray-900">Cuotas pendientes · {{ monthLabel }}</div>
                <div class="text-xs text-gray-500">
                  {{ pendingQuotas.length }} cuotas · {{ selectedQuotas.length }} seleccionadas · {{ selectedUnitsCount }} unidades
                </div>
              </div>
              <div class="text-right">
                <div class="text-lg font-bold text-gray-900">S/. {{ totalSelectedAmount.toFixed(2) }}</div>
                <div v-if="hasCredit" class="text-xs" style="color: #16a34a;">
                  − saldo S/. {{ creditToApply.toFixed(2) }}
                </div>
              </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <q-checkbox
                :model-value="allQuotasSelected"
                color="primary"
                dense
                @update:model-value="toggleAllQuotas"
              />
              <span class="text-sm font-medium text-gray-700">
                {{ allQuotasSelected ? 'Todas las unidades seleccionadas' : 'Seleccionar todas las unidades' }}
              </span>
            </label>
          </div>

          <div class="space-y-4">
            <div v-for="group in pendingQuotaGroups" :key="group.key">
              <div class="flex items-center gap-2 mb-2">
                <q-icon name="eva-calendar-outline" color="grey-7" size="1.1rem" />
                <span class="text-sm font-semibold text-gray-800">{{ group.label }}</span>
                <div class="flex-1 border-t border-gray-200"></div>
              </div>
              <div class="space-y-2">
                <div
                  v-for="quota in group.quotas"
                  :key="quota.id"
                  class="flex items-center justify-between p-3 rounded-lg border cursor-pointer transition-colors"
                  :class="isQuotaSelected(quota) ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-gray-300'"
                  @click="toggleQuota(quota)"
                >
                  <div class="flex items-center gap-3">
                    <q-checkbox
                      :model-value="isQuotaSelected(quota)"
                      color="primary"
                      dense
                      @update:model-value="toggleQuota(quota)"
                    />
                    <div class="flex items-center gap-2">
                      <q-badge
                        :color="quota.departament ? 'blue-1' : 'grey-3'"
                        text-color="grey-9"
                        class="text-xs font-semibold"
                      >
                        {{ unitTypeShort(quota.departament?.type) }}
                      </q-badge>
                      <div>
                        <div class="text-sm font-semibold text-gray-900">
                          Unidad {{ quota.departament?.number || '—' }}
                          <span v-if="quota.departament?.block" class="text-grey-6 font-normal">· B{{ quota.departament.block }}</span>
                        </div>
                        <div v-if="quota.departament?.tenant_pays_quota" class="text-xs text-amber-700">Inquilino paga</div>
                      </div>
                    </div>
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
          </div>
          <div v-if="selectedQuotas.length" class="mt-3 text-right text-sm font-semibold text-gray-900">
            Total seleccionado: S/. {{ totalSelectedAmount.toFixed(2) }}
            <span v-if="hasCredit" class="ml-2 font-medium" style="color: #16a34a;">
              · Saldo a favor: - S/. {{ creditToApply.toFixed(2) }}
            </span>
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
        <div v-if="isQuotaType && selectedUserId && selectedMonth && !pendingQuotas.length && !loading" class="text-center py-6 text-gray-400 text-sm">
          No hay cuotas pendientes para este usuario en el mes seleccionado.
        </div>
        <div v-if="isReserveType && selectedDept && !pendingBookings.length && !loading" class="text-center py-6 text-gray-400 text-sm">
          No hay reservas pendientes de pago para esta unidad.
        </div>

        <!-- Step 3: Payment form -->
        <div v-if="selectedItem || (isQuotaType && selectedQuotas.length)" class=" pt-6 mt-6">
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
                class="form__inputsR"
                prefix="S/."
                borderless
              />
            </div>
            <div v-if="hasCredit" class="col-span-full">
              <div class="px-3 py-2 rounded-lg" style="background: #f0fdf4; border: 1px solid #bbf7d0;">
                <div class="flex justify-between items-center text-sm mb-1">
                  <span class="text-gray-600">Total seleccionado</span>
                  <span class="text-gray-900">S/. {{ totalSelectedAmount.toFixed(2) }}</span>
                </div>
                <div
                  v-for="row in creditAllocations"
                  :key="row.key"
                  class="flex justify-between items-center text-sm"
                  style="color: #16a34a;"
                >
                  <span class="flex items-center gap-1">
                    <q-icon name="eva-checkmark-circle-2-outline" size="1rem" />
                    Saldo a favor · {{ row.label }}
                  </span>
                  <span class="font-medium">- S/. {{ row.applied.toFixed(2) }}</span>
                </div>
                <div
                  class="flex justify-between items-center text-sm font-bold pt-1 mt-1"
                  style="border-top: 1px dashed #86efac;"
                >
                  <span class="text-gray-900">Monto a pagar</span>
                  <span class="text-gray-900">S/. {{ amountToPay.toFixed(2) }}</span>
                </div>
                <div v-if="creditBalance > creditToApply" class="text-xs text-gray-500 mt-1">
                  Saldo total disponible: S/. {{ creditBalance.toFixed(2) }} (lo que supere el monto de cada mes no se aplica)
                </div>
              </div>
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
                class="form__inputsR"
                borderless
              />
            </div>
            <div v-if="selectedPayMethodCommission > 0" class="col-span-full">
              <div class="flex items-center gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200">
                <q-icon name="eva-alert-triangle-outline" color="amber-7" size="20px" />
                <div class="text-sm">
                  <span class="text-amber-800 font-medium">Este método cobra {{ selectedPayMethodCommission }}% de comisión.</span>
                  <span class="text-amber-700 ml-1">Se descontarán S/. {{ commissionAmount.toFixed(2) }} — el condominio recibirá S/. {{ netAmount.toFixed(2) }}</span>
                </div>
              </div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">Fecha de pago *</div>
              <q-input
                v-model="paymentForm.pay_date"
                type="date"
                dense
                class="form__inputsR"
                borderless
              />
            </div>
            <div>
              <div class="text-sm font-medium text-gray-700 mb-1">N° de referencia / operación</div>
              <q-input
                v-model="paymentForm.reference"
                placeholder="000000"
                dense
                class="form__inputsR"
                borderless
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
              class="form__inputsR"
              borderless
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
.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
  &.q-field--auto-height.q-field--dense.q-field--labeled .q-field__control-container {
    padding-top: 10px !important;
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
