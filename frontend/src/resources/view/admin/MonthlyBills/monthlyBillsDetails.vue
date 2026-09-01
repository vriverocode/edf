<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { Notify } from 'quasar'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const monthlyBillsStore = useMonthlyBillsStore()
const quotaStore = useQuotaStore()

const bill = ref(null)
const loading = ref(false)
const generating = ref(false)
const error = ref(null)

const billId = computed(() => route.params.id || route.query.id)

const monthLabel = (month) => {
  const map = {
    1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
    5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
    9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
  }
  return map[month] || `Mes ${month}`
}

const getBillById = async (id) => {
  try {
    loading.value = true
    error.value = null
    const response = await monthlyBillsStore.getMonthlyBillById(id)
    bill.value = response.data
  } catch (err) {
    error.value = err || 'Error al cargar el presupuesto mensual'
  } finally {
    loading.value = false
  }
}

const reload = () => {
  if (billId.value) getBillById(billId.value)
}

const expensesTotal = computed(() => {
  if (!bill.value?.expenses) return 0
  return bill.value.expenses.reduce((sum, e) => sum + Number(e.amount || 0), 0)
})

const commonWaterCost = computed(() => {
  if (!bill.value) return 0
  const consumption = Number(bill.value.common_water_consumption_m3) || 0
  const price = Number(bill.value.water_price_per_m3) || 0
  return Number((consumption * price).toFixed(2))
})

const goToList = () => router.push('/admin/monthly_bills/list')
const goToEdit = () => router.push('/admin/monthly_bills/edit/' + billId.value)
const goTo = (url) => {
  router.push(url)
}
const generateQuotas = async () => {
  generating.value = true
  try {
    const res = await quotaStore.generateMonthlyQuotas(bill.value.month, bill.value.year)
    if (res?.code !== 200) throw res
    Notify.create({ color: 'positive', message: 'Cuotas generadas correctamente', timeout: 2500 })
  } catch (err) {
    Notify.create({ color: 'negative', message: err?.error || err?.message || 'Error al generar cuotas', timeout: 3000 })
  } finally {
    generating.value = false
  }
}

onMounted(() => {
  if (billId.value) {
    getBillById(billId.value)
  } else {
    error.value = 'ID no proporcionado'
  }
})
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-0 md:px-6 px-3 h-full" style="overflow: auto;">
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium">Cargando presupuesto...</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <div class="flex gap-3">
          <q-btn color="primary" outline @click="reload">Reintentar</q-btn>
          <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
        </div>
      </div>

      <div v-else-if="bill" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full">

          <div class="row w-full items-start q-pa-md" style="border-bottom: 1px solid lightgray;">
            <div class="col-md-8 col-8">
              <div class="text-2xl font-bold text-gray-900">
                {{ monthLabel(bill.month) }} {{ bill.year }}
              </div>
              <div class="text-grey-7 mt-1">
                Creado: {{ moment(bill.created_at).format('DD/MM/YYYY') }}
                <span v-if="bill.is_published" class="q-ml-sm text-positive text-bold">Publicado</span>
                <span v-else class="q-ml-sm text-grey-6">Borrador</span>
              </div>
            </div>
            <div class="col-md-4 col-4 text-right">
              <q-btn v-if="!bill.generated_at" color="positive" rounded unelevated :loading="generating" @click="generateQuotas" class="q-mr-sm">
                Generar cuotas
              </q-btn>
              <q-btn color="primary" round outline @click="goToEdit" icon="eva-edit-2-outline" />
            </div>
          </div>

          <div class="q-pa-md" style="border-bottom: 1px solid lightgray;">
            <div class="text-caption text-bold text-grey-6 q-mb-xs" style="text-transform: uppercase; font-size: 10px;">Datos de agua</div>
            <div class="row q-col-gutter-xs">
              <div class="col-md-4 col-12">
                <div class="bg-blue-1 rounded-lg py-2 px-3 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Recibo total</div>
                  <div class="text-subtitle1 text-bold text-blue-9">S/ {{ bill.total_water_bill_amount ? Number(bill.total_water_bill_amount).toFixed(2) : '-' }}</div>
                </div>
              </div>
              <div class="col-md-4 col-12">
                <div class="bg-blue-1 rounded-lg py-2 px-3 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Consumo (m³)</div>
                  <div class="text-subtitle1 text-bold text-blue-9">{{ bill.total_water_consumption_m3 ?? '-' }}</div>
                </div>
              </div>
              <div class="col-md-4 col-12">
                <div class="bg-blue-1 rounded-lg py-2 px-3 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Costo por m³</div>
                  <div class="text-subtitle1 text-bold text-blue-9">S/ {{ Number(bill.water_price_per_m3).toFixed(4) }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="q-pa-md">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">DETALLE DEL PRESUPUESTO</div>

            <table style="width: 100%; border-collapse: collapse;">
              <thead>
                <tr style="background: #f5f5f5;">
                  <th style="text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ddd;">Concepto</th>
                  <th style="text-align: right; padding: 6px 8px; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ddd;">Monto (S/)</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td style="padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 13px;">Presupuesto base mantenimiento</td>
                  <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right; font-size: 13px;">{{ Number(bill.monthly_budget).toFixed(2) }}</td>
                </tr>

                <tr v-if="bill.expenses && bill.expenses.length > 0">
                  <td colspan="2" style="padding: 8px 8px 2px; font-size: 11px; text-transform: uppercase; font-weight: bold; color: #666; border-bottom: 1px solid #ddd;">
                    Gastos del mes ({{ bill.expenses.length }})
                  </td>
                </tr>
                <tr v-for="expense in bill.expenses" :key="expense.id">
                  <td style="padding: 4px 8px 4px 20px; border-bottom: 1px solid #f0f0f0; font-size: 12px; color: #555;">
                    {{ expense.provider?.name || 'N/A' }} — {{ expense.description }}
                    <span v-if="expense.invoice_number" class="text-grey-6"> (Fct. {{ expense.invoice_number }})</span>
                  </td>
                  <td style="padding: 4px 8px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 12px;">{{ Number(expense.amount).toFixed(2) }}</td>
                </tr>
                <tr v-if="bill.expenses && bill.expenses.length > 0">
                  <td style="padding: 4px 8px 4px 20px; font-size: 12px; font-weight: bold; border-bottom: 1px solid #ddd;">Sub-total gastos</td>
                  <td style="padding: 4px 8px; text-align: right; font-size: 12px; font-weight: bold; border-bottom: 1px solid #ddd;">{{ expensesTotal.toFixed(2) }}</td>
                </tr>

                <tr v-if="bill.common_water_consumption_m3 > 0">
                  <td style="padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 13px;">
                    Agua áreas comunes ({{ Number(bill.common_water_consumption_m3).toFixed(2) }} m³ × S/ {{ Number(bill.water_price_per_m3).toFixed(4) }})
                  </td>
                  <td style="padding: 6px 8px; border-bottom: 1px solid #eee; text-align: right; font-size: 13px;">{{ commonWaterCost.toFixed(2) }}</td>
                </tr>

                <tr style="font-weight: bold; background: #f8f8f8;">
                  <td style="padding: 8px; font-size: 14px; border-top: 2px solid #333;">TOTAL A DISTRIBUIR</td>
                  <td style="padding: 8px; text-align: right; font-size: 14px; border-top: 2px solid #333;">S/ {{ Number(bill.total_maintenance_budget).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="q-pa-md" style="border-top: 1px solid lightgray;">
            <div class="text-caption text-bold text-grey-6 q-mb-xs" style="text-transform: uppercase; font-size: 10px;">Resumen del mes</div>
            <div class="row q-col-gutter-xs">
              <div class="col-md-4 col-4">
                <div class="bg-grey-2 rounded-lg py-2 px-2 text-center">
                  <div class="text-grey-6" style="font-size: 10px;">Base mantenimiento</div>
                  <div class="text-subtitle2 text-bold">S/ {{ Number(bill.monthly_budget).toFixed(2) }}</div>
                </div>
              </div>
              <div class="col-md-4 col-4">
                <div class="bg-grey-2 rounded-lg py-2 px-2 text-center">
                  <div class="text-grey-6" style="font-size: 10px;">Gastos del mes</div>
                  <div class="text-subtitle2 text-bold">S/ {{ expensesTotal.toFixed(2) }}</div>
                </div>
              </div>
              <div class="col-md-4 col-4">
                <div class="bg-grey-2 rounded-lg py-2 px-2 text-center">
                  <div class="text-grey-6" style="font-size: 10px;">Agua común</div>
                  <div class="text-subtitle2 text-bold">S/ {{ commonWaterCost.toFixed(2) }}</div>
                </div>
              </div>
            </div>
            <div class="row q-col-gutter-xs q-mt-xs">
              <div class="col-12">
                <div class="bg-positive text-white rounded-lg py-2 px-3 text-center">
                  <div style="font-size: 11px; opacity: 0.85;">Total del mes</div>
                  <div class="text-h6 text-bold">S/ {{ Number(bill.total_maintenance_budget).toFixed(2) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Presupuesto no encontrado</h2>
        <q-btn color="grey-7" outline @click="goToList">Volver</q-btn>
      </div>
    </div>
  </div>
</template>
