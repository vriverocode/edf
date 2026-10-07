<script setup>
import { onMounted, ref, computed } from 'vue'
import { Notify } from 'quasar'
import { useQuotaStore } from '@/services/store/quota.store'

const quotaStore = useQuotaStore()

const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)
const search = ref('')
const loading = ref(false)
const report = ref(null)

const monthNames = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
]

const monthOptions = monthNames.map((label, i) => ({ label, value: i + 1 }))

const rows = computed(() => report.value?.rows || [])

const totals = computed(() => {
  const sum = { water_consumption: 0, water_in_quotas: 0, maintenance: 0, discount: 0, extra: 0, total: 0, pct_total: 0 }
  rows.value.forEach((r) => {
    Object.keys(sum).forEach((k) => { sum[k] += Number(r[k]) || 0 })
  })
  sum.water_consumption = Math.round(sum.water_consumption * 1000) / 1000
  sum.pct_total = Math.round(sum.pct_total * 100000) / 100000
  Object.keys(sum).forEach((k) => { if (k !== 'water_consumption' && k !== 'pct_total') sum[k] = Math.round(sum[k] * 100) / 100 })
  return sum
})

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`
const formatM3 = (v) => (Number(v) || 0).toFixed(3)

const formatPct = (v) => {
  let s = (Number(v) || 0).toFixed(5)
  if (s.includes('.')) s = s.replace(/0+$/, '').replace(/\.$/, '')
  if (s === '' || s === '-') s = '0'
  return '%' + s
}

let debounceTimer = null
const onSearch = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchData, 400)
}

const fetchData = async () => {
  loading.value = true
  try {
    const res = await quotaStore.getUserQuotasReport(year.value, month.value, search.value)
    if (res?.code === 200) report.value = res.data
  } catch (e) {
    Notify.create({ color: 'negative', message: typeof e === 'string' ? e : 'Error al cargar reporte' })
  } finally {
    loading.value = false
  }
}

const exportToXls = async () => {
  loading.value = true
  try {
    await quotaStore.exportUserQuotasReport(year.value, month.value, search.value)
    Notify.create({ color: 'positive', message: 'Archivo descargado correctamente' })
  } catch (e) {
    Notify.create({ color: 'negative', message: typeof e === 'string' ? e : 'Error al exportar archivo' })
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>

<template>
  <div class="md:px-36 px-2 pb-10 h-full" style="overflow: auto;">
    <!-- Toolbar -->
    <div class="row q-mb-md items-center q-col-gutter-sm">
      <!-- Año -->
      <div class="col-6 col-md-2">
        <q-input v-model.number="year" type="number" dense borderless class="form__inputsR" label="Año"
          @update:model-value="fetchData" />
      </div>

      <!-- Mes -->
      <div class="col-6 col-md-2">
        <q-select v-model="month" :options="monthOptions" option-label="label" option-value="value" emit-value
          map-options dense borderless class="form__inputsR" label="Mes" @update:model-value="fetchData" />
      </div>

      <!-- Buscador -->
      <div class="col-12 col-md-4">
        <q-input v-model="search" dense borderless class="form__inputsR" label="Buscar usuario o unidad"
          clearable @update:model-value="onSearch" />
      </div>

      <!-- Resumen -->
      <div class="col-12 col-md text-caption text-grey-7">
        {{ rows.length }} usuario(s) · {{ monthNames[month - 1] }} {{ year }}
      </div>

      <!-- Botón exportar -->
      <div class="col-auto">
        <q-btn color="green" unelevated label="Exportar" icon="eva-download-outline" :disable="rows.length === 0"
          @click="exportToXls" size="sm" />
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>

    <div v-else-if="rows.length === 0" class="text-center text-grey-6 q-py-xl">
      <q-icon name="eva-info-outline" size="4rem" color="grey" />
      <div class="text-h6 q-mt-sm">No hay datos para {{ monthNames[month - 1] }} {{ year }}</div>
    </div>

    <div v-else class="table-wrapper">
      <table class="user-quotas-table">
        <thead>
          <tr>
            <th class="col-user">Usuario</th>
            <th class="col-units">DPT(s)</th>
            <th class="col-pct">% DPT</th>
            <th class="col-units">EST(s)</th>
            <th class="col-pct">% EST</th>
            <th class="col-units">DPO(s)</th>
            <th class="col-pct">% DPO</th>
            <th class="col-pct">% TOTAL</th>
            <th class="col-num">Consumo agua (m³)</th>
            <th class="col-num">Total Agua</th>
            <th class="col-num">Total mantenimiento</th>
            <th class="col-num">Descuento</th>
            <th class="col-num">Cargos extra</th>
            <th class="col-num">Total cuota</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.user_id">
            <td class="col-user text-left">{{ row.user_name }}</td>
            <td class="col-units text-left">{{ row.dpts || '—' }}</td>
            <td class="col-pct text-center">{{ formatPct(row.dpt_pct) }}</td>
            <td class="col-units text-left">{{ row.ests || '—' }}</td>
            <td class="col-pct text-center">{{ formatPct(row.est_pct) }}</td>
            <td class="col-units text-left">{{ row.dpos || '—' }}</td>
            <td class="col-pct text-center">{{ formatPct(row.dpo_pct) }}</td>
            <td class="col-pct text-center">{{ formatPct(row.pct_total) }}</td>
            <td class="col-num text-right">{{ formatM3(row.water_consumption) }}</td>
            <td class="col-num text-right">{{ formatMoney(row.water_in_quotas) }}</td>
            <td class="col-num text-right">{{ formatMoney(row.maintenance) }}</td>
            <td class="col-num text-right text-positive">{{ formatMoney(row.discount) }}</td>
            <td class="col-num text-right text-orange-7">{{ formatMoney(row.extra) }}</td>
            <td class="col-num text-right text-bold">{{ formatMoney(row.total) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="totals-row">
            <td class="col-user text-left text-bold">TOTAL</td>
            <td class="col-units"></td>
            <td class="col-pct text-center"></td>
            <td class="col-units"></td>
            <td class="col-pct text-center"></td>
            <td class="col-units"></td>
            <td class="col-pct text-center"></td>
            <td class="col-pct text-center">{{ formatPct(totals.pct_total) }}</td>
            <td class="col-num text-right">{{ formatM3(totals.water_consumption) }}</td>
            <td class="col-num text-right">{{ formatMoney(totals.water_in_quotas) }}</td>
            <td class="col-num text-right">{{ formatMoney(totals.maintenance) }}</td>
            <td class="col-num text-right">{{ formatMoney(totals.discount) }}</td>
            <td class="col-num text-right">{{ formatMoney(totals.extra) }}</td>
            <td class="col-num text-right">{{ formatMoney(totals.total) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<style scoped>
:deep(.form__inputsR .q-field__inner) {
  background: #ffffff;
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}

@media (max-width: 780px) {
  :deep(.form__inputsR .q-field__inner) {
    padding: 0.1rem 1rem;
  }
}

.table-wrapper {
  overflow-x: auto;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 4px;
}

.user-quotas-table {
  border-collapse: collapse;
  width: 100%;
  min-width: 1200px;
  font-size: 0.8125rem;
  font-family: inherit;
}

.user-quotas-table th,
.user-quotas-table td {
  border: 1px solid rgba(0, 0, 0, 0.12);
  padding: 7px 10px;
  white-space: nowrap;
}

.user-quotas-table thead th {
  background: #f5f5f5;
  color: rgba(0, 0, 0, 0.54);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  font-size: 0.7rem;
  text-align: center;
  position: sticky;
  top: 0;
  z-index: 2;
}

/* Columna Usuario sticky (izquierda) */
.col-user {
  position: sticky;
  left: 0;
  z-index: 1;
  background: #ffffff;
  width: 170px;
  min-width: 170px;
}

.user-quotas-table thead .col-user {
  z-index: 3;
  background: #f5f5f5;
}

.totals-row .col-user {
  background: #e3f2fd !important;
}

.col-units {
  min-width: 120px;
}

.col-pct {
  width: 84px;
  min-width: 84px;
  text-align: center;
}

.col-num {
  min-width: 110px;
  text-align: right;
}

.user-quotas-table tbody tr:hover td {
  filter: brightness(0.97);
}

.totals-row td {
  font-weight: 700;
  border-top: 2px solid rgba(0, 0, 0, 0.12);
  background: #e3f2fd !important;
  color: #1565c0;
}

.totals-row .text-bold {
  font-weight: 700;
}
</style>
