<script setup>
import { ref, computed, onMounted } from 'vue'
import { useQuotaStore } from '@/services/store/quota.store'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useRoute, useRouter } from 'vue-router'
import moment from 'moment'
import appIcons from '@/assets/icons'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const MONTH_LABELS = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
]

const route = useRoute()
const router = useRouter()
const quotaStore = useQuotaStore()
const apartmentStore = useApartmentStore()

const loading = ref(true)
const cards = ref([])
const yearFilter = ref('all')
const monthFilter = ref('all')

const isAdmin = computed(() => route.path.startsWith('/admin'))

const departamentId = computed(() => {
  const id = Number(route.query.departament_id)
  return Number.isInteger(id) && id > 0 ? id : null
})

const departamentNumber = computed(() => route.query.number ?? null)

const ownerId = computed(() => {
  const id = Number(route.query.user_id)
  return Number.isInteger(id) && id > 0 ? id : null
})

// Alcance admin: la unidad elegida + las demás unidades del mismo
// propietario (los montos totales de cada card incluyen todas sus unidades).
const scopeDeptIds = ref([])

const isAdminWithoutDept = computed(() => isAdmin.value && !departamentId.value)

const monthOptions = [
  { label: 'Todos', value: 'all' },
  ...MONTH_LABELS.map((label, i) => ({ label, value: i + 1 })),
]

const yearOptions = computed(() => {
  const years = [...new Set(cards.value.map(c => Number(c.year)))].sort((a, b) => b - a)
  return [{ label: 'Todos', value: 'all' }, ...years.map(y => ({ label: String(y), value: y }))]
})

const filteredCards = computed(() =>
  cards.value
    .filter(c =>
      (yearFilter.value === 'all' || Number(c.year) === Number(yearFilter.value)) &&
      (monthFilter.value === 'all' || Number(c.month) === Number(monthFilter.value))
    )
    .sort((a, b) => (b.year * 100 + b.month) - (a.year * 100 + a.month))
)

// Estado general del mes derivado de los montos (admin y client comparten forma)
const cardStatus = (card) => {
  if (!card.units_count) {
    return { color: 'grey', icon: 'eva-file-outline', label: 'Sin cuotas' }
  }
  if (card.has_pending_validation) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Validando pago' }
  }
  if (card.total_pending > 0) {
    if (card.total_paid > 0) {
      return { color: 'info', icon: 'eva-clock-outline', label: 'Parcial' }
    }
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente' }
  }
  return { color: 'positive', icon: 'eva-checkmark-circle-2-outline', label: 'Pagado' }
}

const paidPercent = (card) => {
  const total = Number(card.total_amount || 0)
  if (total <= 0) return 0
  return Math.min(1, Math.round((Number(card.total_paid || 0) / total) * 100) / 100)
}

const formatMoney = (value) => `S/. ${Number(value ?? 0).toFixed(2)}`

const formatDate = (date) => (date ? moment(date).format('DD MMM YYYY') : '')

// Cliente: los grupos de GET /api/quotas (mes_año, con variante _tenant) se
// fusionan en una sola card por mes con los mismos campos que el resumen admin.
const normalizeClientGroups = (groups) => {
  const byPeriod = new Map()

  for (const group of groups) {
    const month = Number(group.month)
    if (!month) continue
    const year = group.due_date
      ? Number(String(group.due_date).slice(0, 4))
      : new Date().getFullYear()
    const key = `${year}-${month}`

    if (!byPeriod.has(key)) {
      byPeriod.set(key, {
        month,
        year,
        month_label: MONTH_LABELS[month - 1] || String(month),
        due_date: group.due_date || null,
        maintenance_amount: null,
        water_amount: null,
        water_quota_id: null,
        total_amount: 0,
        total_paid: 0,
        total_pending: 0,
        units_count: 0,
        owners_count: 0,
        pending_validation_count: 0,
        has_pending_validation: false,
      })
    }

    const card = byPeriod.get(key)
    card.total_amount += Number(group.amount || 0)
    if (group.maintenance_amount != null) {
      card.maintenance_amount = (card.maintenance_amount ?? 0) + Number(group.maintenance_amount || 0)
    }
    if (group.water_amount != null) {
      card.water_amount = (card.water_amount ?? 0) + Number(group.water_amount || 0)
    }
    if (group.due_date) card.due_date = group.due_date

    const ownerIds = card._ownerIds || (card._ownerIds = new Set())
    const payIds = card._payIds || (card._payIds = new Set())
    const details = Array.isArray(group.details) ? group.details : []

    for (const detail of details) {
      card.units_count += 1
      const ownerId = detail?.departament?.owner?.id ?? detail?.departament?.user_id
      if (ownerId) ownerIds.add(ownerId)

      if (!card.water_quota_id && detail.water_reading_id) {
        card.water_quota_id = detail.id
      }

      const status = Number(detail.status)
      const amount = Number(detail.amount || 0)
      if (status === 3) card.total_paid += amount
      else if (status === 1 || status === 2) card.total_pending += amount

      const pays = Array.isArray(detail.pays) ? detail.pays : []
      for (const pay of pays) {
        if (Number(pay.status) === 1 && pay.id) payIds.add(pay.id)
      }
    }
  }

  return [...byPeriod.values()]
    .map(card => {
      card.total_amount = Math.round(card.total_amount * 100) / 100
      card.total_paid = Math.round(card.total_paid * 100) / 100
      card.total_pending = Math.round(card.total_pending * 100) / 100
      if (card.maintenance_amount != null) card.maintenance_amount = Math.round(card.maintenance_amount * 100) / 100
      if (card.water_amount != null) card.water_amount = Math.round(card.water_amount * 100) / 100
      card.owners_count = card._ownerIds ? card._ownerIds.size : 0
      card.pending_validation_count = card._payIds ? card._payIds.size : 0
      card.has_pending_validation = card.pending_validation_count > 0
      delete card._ownerIds
      delete card._payIds
      return card
    })
    .sort((a, b) => (b.year * 100 + b.month) - (a.year * 100 + a.month))
}

const loadData = async () => {
  loading.value = true
  try {
    if (isAdmin.value) {
      if (!departamentId.value) {
        cards.value = []
        return
      }
      // Alcance: todas las unidades del propietario de la unidad elegida
      // (si el link no trae user_id, se queda solo con la unidad).
      let ids = [departamentId.value]
      if (ownerId.value) {
        try {
          // Solo unidades que posee (DEP, EST, DPO, LAV), sin inquilinos.
          const res = await apartmentStore.getDepartmentsByOwner(ownerId.value, { ownedOnly: true })
          const depts = res.data || []
          if (depts.length) ids = depts.map(d => d.id).filter(Boolean)
        } catch {}
      }
      scopeDeptIds.value = ids
      const res = await quotaStore.getAdminMonthlySummary({ departament_ids: ids })
      if (res.code !== 200) throw res
      cards.value = res.data || []
    } else {
      // Cliente: getQuotasByUser ya trae todas las unidades del usuario.
      scopeDeptIds.value = []
      const res = await quotaStore.getQuotasByUser()
      if (res.code !== 200) throw res
      cards.value = normalizeClientGroups(res.data || [])
    }
  } catch (err) {
    console.error(err)
    cards.value = []
  } finally {
    loading.value = false
  }
}

const goToMonth = (card) => {
  // Igual que quotasMaintenanceMonthDetail: redirect al resumen del mes por owner.
  if (isAdmin.value) {
    if (ownerId.value) {
      router.push(`/admin/quota/details/month/${card.month}?year=${card.year}&owner=${ownerId.value}`)
      return
    }
    // Fallback de links antiguos sin user_id
    router.push({
      name: 'quotasDetailByMonthAdmin',
      params: { month: String(card.month) },
      query: {
        year: String(card.year),
        ...(scopeDeptIds.value.length ? { departament_ids: scopeDeptIds.value.join(',') } : {}),
      },
    })
    return
  }
  router.push({
    name: 'quotaDeailByMonth',
    params: { month: String(card.month) },
    query: { year: String(card.year) },
  })
}

const waterDetailHref = (card) =>
  card.water_quota_id
    ? router.resolve({ name: 'quotaWaterDetailClient', params: { id: String(card.water_quota_id) } }).href
    : ''

const openWaterDetail = (card) => {
  if (!card.water_quota_id) return
  router.push({ name: 'quotaWaterDetailClient', params: { id: String(card.water_quota_id) } })
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 pt-2 md:px-28 pb-12">
        <!-- Unidad seleccionada -->
        <div v-if="departamentNumber !== null" class="flex items-center text-weight-medium q-mb-sm md:px-8">
          <span class="text-subtitle1 text-weight-medium">
            Cuotas — Unidad #{{ departamentNumber }}
            <template v-if="isAdmin && scopeDeptIds.length > 1"> · {{ scopeDeptIds.length }} unidades del propietario</template>
            <template v-else-if="!isAdmin"> · todas tus unidades</template>
          </span>
        </div>
        <div v-if="filteredCards.length > 0" class="md:px-5 row">
          <div v-for="card in filteredCards" :key="`${card.year}-${card.month}`"
            class="col-md-4 col-12 md:px-2 mb-5 md:mb-4">
            <div class="bg-white rounded-xl shadow-md overflow-hidden h-full w-full  cursor-pointer"
              style="border: 1px solid lightgrey" >

              <div class="px-4 pb-0 pt-2 md:pt-4">
                <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                  <h3 class="text-lg font-bold text-gray-900 m-0">
                    Mensualidad: {{ card.month_label }} {{ card.year }}
                  </h3>
                  <q-badge v-if="card.has_pending_validation" color="warning" class="text-white px-2 py-1">
                    {{ card.pending_validation_count }} pago(s) por validar
                  </q-badge>
                </div>

                <div class="py-3">
                  <!-- Mantenimiento / Agua (solo el detalle client los trae) -->
                  <div v-if="card.maintenance_amount != null || card.water_amount != null" class="row items-center">
                    <div v-if="card.maintenance_amount != null" class="flex items-center text-sm text-gray-700 col-6">
                      <span class="text-gray-500">Mantenimiento:</span>
                      <span class="font-medium ml-1">{{ formatMoney(card.maintenance_amount) }}</span>
                    </div>
                    <div v-if="card.water_amount != null" class="flex items-center text-sm text-gray-700"
                      :class="card.maintenance_amount != null ? 'col-6 justify-end' : 'col-6'">
                      <span class="text-gray-500">Agua:</span>
                      <a v-if="card.water_quota_id" :href="waterDetailHref(card)"
                        class="font-medium ml-1 text-primary" style="text-decoration: underline;"
                        @click.stop.prevent="openWaterDetail(card)">
                        {{ formatMoney(card.water_amount) }}
                      </a>
                      <span v-else class="font-medium ml-1">{{ formatMoney(card.water_amount) }}</span>
                    </div>
                  </div>
                  <!-- Total del mes -->
                  <div class="flex items-center text-sm">
                    <span class="text-gray-500">Total del mes:</span>
                    <span class="font-bold text-gray-900 ml-1">{{ formatMoney(card.total_amount) }}</span>
                  </div>
                  <div class="flex flex-wrap text-sm text-gray-600 pt-2">
                    <span class="flex items-center mr-1">
                      <q-icon name="eva-home-outline" size="1rem" class="q-mr-xs" />
                      {{ card.units_count }} unidad(es)
                    </span>
                    <span v-if="card.due_date" class="ml-2">Fecha límite: {{ formatDate(card.due_date) }}</span>
                  </div>
                  <div v-if="isAdmin" class="row items-center pt-2">
                    <div class="flex items-center text-sm col-6">
                      <q-icon name="eva-checkmark-circle-outline" size="1.1rem" class="text-positive pr-1" />
                      <span class="text-gray-500">Pagado:</span>
                      <span class="font-medium text-positive p-1">{{ formatMoney(card.total_paid) }}</span>
                    </div>
                    <div class="flex items-center text-sm col-6">
                      <q-icon name="eva-alert-circle-outline" size="1.1rem" class="text-warning pr-1" />
                      <span class="text-gray-500">Pendiente:</span>
                      <span class="font-medium text-warning p-1">{{ formatMoney(card.total_pending) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Footer de estado + acción -->
              <div class="px-4 py-2 md:py-3 border-t flex justify-between items-center cursor-pointer"
                :class="`bg-${cardStatus(card).color}`">
                <div class="flex items-center">
                  <q-icon :name="cardStatus(card).icon" color="white" size="1.5rem" />
                  <span class="ml-1 text-sm font-medium text-white">
                    {{ cardStatus(card).label }} — Total {{ formatMoney(card.total_amount) }}
                  </span>
                </div>
                <div class="flex items-center text-white text-sm font-medium" @click="goToMonth(card)">
                  <span>Ver cuotas</span>
                  <q-icon name="eva-arrow-forward-outline" color="white" size="1.25rem" class="q-ml-xs" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <div v-html="appIcons.mensuality" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">
            {{ isAdminWithoutDept ? 'Selecciona un departamento' : 'No hay cuotas registradas' }}
          </h3>
          <p class="text-gray-600 text-center mb-6">
            <template v-if="isAdminWithoutDept">
              Abre esta vista desde la lista de departamentos para ver las cuotas acumuladas de una unidad.
            </template>
            <template v-else-if="cards.length > 0">
              No se encontraron cuotas para el mes/año seleccionado.
            </template>
            <template v-else>
              Aún no se ha emitido ninguna cuota mensual.
            </template>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.form__inputsStatus {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
    background: white;
  }
}
</style>
