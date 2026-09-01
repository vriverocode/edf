<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '@/services/store/expense.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const expenseStore = useExpenseStore()

const expense = ref(null)
const loading = ref(true)
const error = ref(null)

const expenseId = computed(() => route.params.id)

const monthLabel = (month) => {
  const map = {
    1: 'Enero', 2: 'Febrero', 3: 'Marzo', 4: 'Abril',
    5: 'Mayo', 6: 'Junio', 7: 'Julio', 8: 'Agosto',
    9: 'Septiembre', 10: 'Octubre', 11: 'Noviembre', 12: 'Diciembre'
  }
  return map[month] || `Mes ${month}`
}

const formatMoney = (value) => {
  const n = Number(value)
  if (!Number.isFinite(n)) return '0.00'
  const fixed = n.toFixed(2)
  const [intPart, decPart] = fixed.split('.')
  const withThousands = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return `${withThousands},${decPart}`
}

const formatDate = (date) => {
  if (!date) return '—'
  return moment(date).format('DD/MM/YYYY')
}

const statusClass = (status) => {
  if (status === 1) return 'bg-orange-500'
  if (status === 2) return 'bg-blue-500'
  if (status === 3) return 'bg-green-500'
  return 'bg-grey-5'
}

const isImage = (url) => {
  if (!url) return false
  return /\.(jpg|jpeg|png|webp)$/i.test(url)
}

const isPdf = (url) => {
  if (!url) return false
  return /\.pdf$/i.test(url)
}

const fileName = (url) => {
  if (!url) return ''
  return url.split('/').pop() || ''
}

const goBack = () => router.go(-1)
const goToEdit = () => router.push('/admin/expenses/edit/' + expenseId.value)
const goToPay = () => router.push('/admin/expenses/pay/' + expenseId.value)
const openAttachment = () => {
  if (expense.value?.attachment_url) {
    window.open(expense.value.attachment_url, '_blank')
  }
}

const fetchExpense = async () => {
  try {
    loading.value = true
    error.value = null
    const response = await expenseStore.getExpenseById(expenseId.value)
    if (response?.code !== 200) throw response
    expense.value = response.data
  } catch (err) {
    error.value = err?.error || err?.message || 'Error al cargar el gasto'
  } finally {
    loading.value = false
  }
}

const monthlyBillLabel = (bill) => {
  if (!bill) return 'Sin asignar'
  return `${monthLabel(bill.month)} ${bill.year}`
}

onMounted(() => {
  if (expenseId.value) {
    fetchExpense()
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
        <p class="text-gray-600 font-medium">Cargando gasto...</p>
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
          <q-btn color="primary" outline @click="fetchExpense">Reintentar</q-btn>
          <q-btn color="grey-7" outline @click="goBack">Volver</q-btn>
        </div>
      </div>

      <div v-else-if="expense" class="flex flex-col items-center md:px-28 md:mx-28">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 w-full">

          <div class="row w-full items-start q-pa-md-md q-pa-sm"  style="border-bottom: 1px solid lightgray;">
            <div class="col-md-8 col-7">
              <div class="text-2xl font-bold text-gray-900">
                {{ expense.provider?.name || 'Proveedor' }}
              </div>
              <div class="text-grey-7 mt-1">
                {{ expense.service_category?.name || 'Sin categoría' }}
              </div>
            </div>
            <div class="col-md-4 col-5 text-right">
              <div class="mx-1">
                <span
                  :class="statusClass(expense.status)"
                  class="inline-block px-3 py-1 text-xs font-bold text-white rounded-full q-mr-sm"
                >
                  {{ expense.status_label }}
                </span>
              </div>
              <div class="mt-2 flex justify-end">
                <q-btn color="primary"  class=" mx-1 md:px-12" rounded outline @click="goToEdit" icon="eva-edit-2-outline" />
                <q-btn color="secondary"  class=" mx-1 md:px-12" rounded outline @click="goToPay" >
                 <svg width="2rem" height="1.5rem" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" fill="#c8a34b"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <g id="Layer_2" data-name="Layer 2"> <g id="invisible_box" data-name="invisible box"> <rect width="48" height="48" fill="none"></rect> </g> <g id="Icons"> <g> <path d="M42.2,31.7a4.6,4.6,0,0,0-4-1.1l-9.9,1.7A4.7,4.7,0,0,0,26.9,29l-7.1-7H5a2,2,0,0,0,0,4H18.2l5.9,5.9a.8.8,0,0,1,0,1.1.9.9,0,0,1-1.2,0l-3.5-3.5a2.1,2.1,0,0,0-2.8,0,2.1,2.1,0,0,0,0,2.9l3.5,3.4a4.5,4.5,0,0,0,3.4,1.4,5.7,5.7,0,0,0,1.8-.3h0l13.6-2.4a1,1,0,0,1,.8.2,1.1,1.1,0,0,1,.3.7,1,1,0,0,1-.8,1L20.6,39.8,9.7,30.9H5a2,2,0,0,0,0,4H8.3L19.4,44l20.5-3.7A4.9,4.9,0,0,0,44,35.4,4.6,4.6,0,0,0,42.2,31.7Z"></path> <path d="M34.3,20.1h0a6.7,6.7,0,0,1-4.1-1.3,2,2,0,0,0-2.8.6,1.8,1.8,0,0,0,.3,2.6A10.9,10.9,0,0,0,32,23.8V26a2,2,0,0,0,4,0V23.8a6.3,6.3,0,0,0,3-1.3,4.9,4.9,0,0,0,2-4h0c0-3.7-3.4-4.9-6.3-5.5s-3.5-1.3-3.5-1.8.2-.6.5-.9a3.4,3.4,0,0,1,1.8-.4,6.3,6.3,0,0,1,3.3.9,1.8,1.8,0,0,0,2.7-.5,1.9,1.9,0,0,0-.4-2.8A9.1,9.1,0,0,0,36,6.3V4a2,2,0,0,0-4,0V6.2c-3,.5-5,2.5-5,5.2s3.3,4.9,6.5,5.5,3.3,1.3,3.3,1.8S35.7,20.1,34.3,20.1Z"></path> </g> </g> </g> </g></svg>
                </q-btn>
              </div>

            </div>
          </div>

          <div class="q-pa-md">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">INFORMACIÓN DEL GASTO</div>

            <div class="row q-col-gutter-sm">
              <div class="col-md-6 col-12">
                <div class="text-caption text-grey-6">Proveedor</div>
                <div class="text-body1 text-bold">{{ expense.provider?.name || '—' }}</div>
              </div>
              <div class="col-md-6 col-12">
                <div class="text-caption text-grey-6">Categoría de servicio</div>
                <div class="text-body1 text-bold">{{ expense.service_category?.name || '—' }}</div>
              </div>
              <div class="col-md-4 col-12">
                <div class="text-caption text-grey-6">N° Factura</div>
                <div class="text-body1 text-bold">{{ expense.invoice_number || '—' }}</div>
              </div>
              <div class="col-md-4 col-12">
                <div class="text-caption text-grey-6">Tipo</div>
                <div class="text-body1 text-bold">{{ expense.expense_type_label }}</div>
              </div>
              <div class="col-md-4 col-12">
                <div class="text-caption text-grey-6">Estado</div>
                <div class="text-body1 text-bold">{{ expense.status_label }}</div>
              </div>
            </div>
          </div>

          <div class="q-pa-md" style="border-top: 1px solid lightgray;">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">MONTO Y FECHAS</div>

            <div class="row q-col-gutter-sm">
              <div class="col-md-4 col-12">
                <div class="bg-blue-1 rounded-lg py-3 px-4 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Monto</div>
                  <div class="text-h5 text-bold text-blue-9">S/ {{ formatMoney(expense.amount) }}</div>
                </div>
              </div>
              <div class="col-md-4 col-12">
                <div class="bg-grey-2 rounded-lg py-3 px-4 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Fecha emisión</div>
                  <div class="text-subtitle1 text-bold">{{ formatDate(expense.issue_date) }}</div>
                </div>
              </div>
              <div class="col-md-4 col-12">
                <div class="bg-grey-2 rounded-lg py-3 px-4 text-center">
                  <div class="text-caption text-grey-6" style="font-size: 10px;">Fecha vencimiento</div>
                  <div class="text-subtitle1 text-bold">{{ formatDate(expense.due_date) }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="q-pa-md" style="border-top: 1px solid lightgray;">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">UBICACIÓN Y DESCRIPCIÓN</div>

            <div class="row q-col-gutter-sm">
              <div v-if="expense.location_scope" class="col-md-6 col-12">
                <div class="text-caption text-grey-6">Ubicación</div>
                <div class="text-body1 text-bold">{{ expense.location_scope }}</div>
              </div>
              <div v-if="expense.unit" class="col-md-6 col-12">
                <div class="text-caption text-grey-6">Unidad</div>
                <div class="text-body1 text-bold">{{ expense.unit }}</div>
              </div>
              <div class="col-12">
                <div class="text-caption text-grey-6 q-mb-xs">Descripción</div>
                <div class="bg-grey-2 rounded-lg p-3 text-body2" style="white-space: pre-wrap;">{{ expense.description }}</div>
              </div>
            </div>
          </div>

          <div v-if="expense.attachment_url" class="q-pa-md" style="border-top: 1px solid lightgray;">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">DOCUMENTO ADJUNTO</div>

            <div class="bg-grey-2 rounded-lg overflow-hidden">
              <div v-if="isImage(expense.attachment_url)" class="flex justify-center p-3">
                <img :src="expense.attachment_url" class="rounded-lg" style="max-height: 300px; max-width: 100%; object-fit: contain;" />
              </div>
              <div v-else class="flex items-center gap-3 p-4">
                <div class="bg-red-1 rounded-lg p-3">
                  <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                  </svg>
                </div>
                <div class="flex-1">
                  <div class="text-body2 text-bold text-grey-9">{{ fileName(expense.attachment_url) }}</div>
                  <div class="text-caption text-grey-6">Archivo adjunto</div>
                </div>
              </div>
              <div class="flex justify-end gap-2 q-pa-sm" style="border-top: 1px solid #e0e0e0;">
                <q-btn flat dense color="primary" icon="eva-eye-outline" label="Ver" @click="openAttachment" />
                <q-btn flat dense color="primary" icon="eva-download-outline" label="Descargar"
                  :href="expense.attachment_url" download />
              </div>
            </div>
          </div>

          <div v-if="expense.monthly_bill" class="q-pa-md" style="border-top: 1px solid lightgray;">
            <div class="text-subtitle1 text-bold text-grey-8 q-mb-sm">PRESUPUESTO MENSUAL ASOCIADO</div>
            <div class="bg-grey-2 rounded-lg py-2 px-4">
              <span class="text-body1 text-bold">{{ monthlyBillLabel(expense.monthly_bill) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="flex flex-col items-center justify-center py-20">
        <h2 class="text-xl font-bold text-gray-900 mb-2">Gasto no encontrado</h2>
        <q-btn color="grey-7" outline @click="goBack">Volver</q-btn>
      </div>
    </div>
  </div>
</template>
