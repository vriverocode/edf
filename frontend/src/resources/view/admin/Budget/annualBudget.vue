<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify, Dialog } from 'quasar'
import { useAnnualBudgetStore } from '@/services/store/annualBudget.store'
import { useRouter } from 'vue-router'
import { usePaginationState } from '@/composables/usePaginationState'

const annualBudgetStore = useAnnualBudgetStore()
const router = useRouter()
const loading = ref(false)
const ready = ref(false)
const budgets = ref([])
const lastPage = ref(1)

const { page, restoreFromQuery, onPageChange } = usePaginationState()

const statusLabel = (status) => {
  const map = { 1: 'Borrador', 2: 'Activo', 3: 'Cerrado' }
  return map[status] || 'Desconocido'
}

const statusColor = (status) => {
  const map = { 1: 'grey', 2: 'positive', 3: 'negative' }
  return map[status] || 'grey'
}


const fetchBudgets = async () => {
  loading.value = true
  ready.value = false
  try {
    const response = await annualBudgetStore.getAnnualBudgets({
      page: page.value,
      per_page: 12,
    })
    if (response?.code !== 200) throw response
    const payload = response.data || {}
    const pagination = payload.pagination || {}
    budgets.value = pagination.data || []
    lastPage.value = pagination.last_page || 1
    ready.value = true
  } catch (err) {
    const apiError = err?.error || err?.message || 'No se pudo cargar la lista de presupuestos'
    Notify.create({ color: 'negative', message: apiError, timeout: 2000 })
  } finally {
    loading.value = false
  }
}


const goTo = (url) => router.push(url)

const confirmDelete = (budget) => {
  Dialog.create({
    title: 'Eliminar presupuesto',
    message: `¿Estás seguro de eliminar "${budget.name}"? Esta acción no se puede deshacer.`,
    cancel: { label: 'Cancelar', flat: true, color: 'grey' },
    ok: { label: 'Eliminar', color: 'negative' },
    persistent: true,
  }).onOk(async () => {
    try {
      const response = await annualBudgetStore.deleteAnnualBudget(budget.id)
      if (response?.code !== 200) throw response
      Notify.create({ color: 'positive', message: 'Presupuesto eliminado correctamente', timeout: 2000 })
      fetchBudgets()
    } catch (err) {
      const apiError = err?.error || err?.message || 'No se pudo eliminar el presupuesto'
      Notify.create({ color: 'negative', message: apiError, timeout: 2000 })
    }
  })
}

onMounted(() => {
  restoreFromQuery()
  fetchBudgets()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div style="height: 100%; overflow: hidden;">
      <div class="px-2 pb-6 pt-0 md:px-28 h-full">
        <!-- ACTION BAR -->
        <div v-if="!loading && ready" style="height: 10%;">
          <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-full">
            <q-btn
              color="primary"
              unelevated
              class="w-full mt-5 md:mx-5"
              style="border-radius: 0.5rem;"
              @click="goTo('/admin/budget/annual/create')"
            >
              <div class="flex items-center py-1">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">Crear nuevo</div>
              </div>
            </q-btn>
          </div>
        </div>
        <!-- LOADING -->
        <div v-if="loading && !ready" class="flex justify-center items-center py-20" style="height: 90%;">
          <q-spinner-dots color="primary" size="7rem" />
        </div>
        <!-- CONTENT -->
        <div v-else class="pt-3 md:px-5 pb-8" style="height: 80%; overflow: auto;">
          <template v-if="budgets.length > 0">
            <div class="row pt-2">
              <div v-for="budget in budgets" :key="budget.id" 
                class="bg-white mb-5 col-12 col-md-3 cursor-pointer" 
                style="border: 2px solid lightgray; border-radius: 1rem; position: relative;">
                <div class="pb-4 pt-2">
                  <div class="flex justify-between items-center pb-1 px-4" style="border-bottom: 1px solid lightgrey;">
                    <div class="flex items-center">
                      <div class="text-lg font-bold text-gray-900">Presupuesto año {{ budget.year }}</div>
  
                      <q-chip :color="budget.status === 1 ? 'grey' : budget.status === 2 ? 'positive' : 'negative'"  class="ml-2"  text-color="white">
                        <div class="px-2 py-1">
                          {{ statusLabel(budget.status) }}
                        </div>
                      </q-chip>
                    </div>
                    <div class="flex items-center ">
                      <q-btn
                        flat
                        round
                        dense
                        icon="eva-menu-outline"
                        size="sm"
                      >
                        <q-menu>
                          <q-list style="min-width: 150px;">
                            <q-item clickable v-close-popup @click="goTo(`/admin/budget/annual/${budget.id}`)">
                              <q-item-section avatar><q-icon name="eva-eye-outline" /></q-item-section>
                              <q-item-section>Ver detalle</q-item-section>
                            </q-item>
                            <q-item clickable v-close-popup @click="goTo(`/admin/budget/annual/${budget.id}/edit`)">
                              <q-item-section avatar><q-icon name="eva-edit-2-outline" /></q-item-section>
                              <q-item-section>Editar</q-item-section>
                            </q-item>
                            <q-item clickable v-close-popup @click="confirmDelete(budget)">
                              <q-item-section avatar><q-icon name="eva-trash-2-outline" color="negative" /></q-item-section>
                              <q-item-section class="text-negative">Eliminar</q-item-section>
                            </q-item>
                          </q-list>
                        </q-menu>
                      </q-btn>
                    </div>
                  </div>
                  <div class="row px-4 pt-3">
                    <div class="col-6">
                      <div class="text-caption text-grey-6">Año</div>
                      <div class="text-body1 font-bold">{{ budget.year }}</div>
                    </div>
                    <div class="col-6  flex flex-col items-end">
                      <div class="text-caption text-grey-6">Creado</div>
                      <div class="text-body1">{{ new Date(budget.created_at).toLocaleDateString() }}</div>
                    </div>
                    <div class="col-6 pt-2">
                      <div class="text-caption text-grey-6">Total mensual</div>
                      <div class="text-body1 font-bold">S/. {{ Number(budget.total_monthly_budget || 0).toFixed(2) }}</div>
                    </div>
                    <div class="col-6 pt-2 flex flex-col items-end">
                      <div class="text-caption text-grey-6">Presupuesto anual aprobado</div>
                      <div class="text-body1 font-bold">S/. {{ Number(budget.total_monthly_budget * 12 || 0).toFixed(2) }}</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex justify-center mt-4">
              <q-pagination
                v-model="page"
                color="primary"
                :max="lastPage"
                :max-pages="4"
                :boundary-numbers="false"
                @update:model-value="onPageChange(fetchBudgets)"
              />
            </div>
          </template>
          <template v-else>
            <div class="flex flex-col items-center justify-center py-20">
              <div class="bg-blue-1 rounded-full p-6 mb-4">
                <q-icon name="eva-file-text-outline" size="3rem" color="primary" />
              </div>
              <div class="text-lg font-semibold text-gray-900 mb-1">Sin presupuestos anuales</div>
              <div class="text-gray-600 text-center mb-4">Crea un presupuesto anual para comenzar</div>
              <q-btn color="primary" unelevated style="border-radius: 0.5rem;" @click="goTo('/admin/budget/annual/create')">
                <q-icon name="eva-plus-outline" class="q-mr-sm" />
                Crear presupuesto
              </q-btn>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.form__inputsR .q-field__inner {
  box-shadow: 0px 3px 4px 0px #bfbfbf48;
  border-radius: 0.5rem;
  border: 1px solid rgb(223, 223, 223);
  padding: 0px 1rem;
}
</style>
