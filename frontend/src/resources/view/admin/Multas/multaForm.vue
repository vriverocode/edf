<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { useMultaStore } from '@/services/store/multa.store'
import { useApartmentStore } from '@/services/store/apartment.store'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const multaStore = useMultaStore()
const apartmentStore = useApartmentStore()

const isEdit = computed(() => !!route.params.id)
const loading = ref(false)
const saving = ref(false)
const loadingDepartments = ref(false)
const loadingRules = ref(false)

const departments = ref([])
const filteredDepartments = ref([])
const rules = ref([])

const form = ref({
  departament_id: null,
  rule_id: null,
  description: '',
  incident_date: new Date().toISOString().substring(0, 10),
  amount: 0,
  installments: 1,
  start_month: new Date().getMonth() + 1,
  start_year: new Date().getFullYear(),
  status: 1,
})

const monthlyAmount = computed(() => {
  const inst = Number(form.value.installments) || 1
  return (Number(form.value.amount) || 0) / inst
})

const monthOptions = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
].map((m, i) => ({ label: m, value: i + 1 }))

const statusOptions = [
  { label: 'Pendiente / Activo', value: 1 },
  { label: 'Pagado', value: 2 },
  { label: 'Anulado', value: 3 },
]

const onRuleSelected = (ruleId) => {
  if (!ruleId) return
  const rule = rules.value.find((r) => r.id === ruleId)
  if (rule) {
    if (!form.value.description) {
      form.value.description = `${rule.code ? `[${rule.code}] ` : ''}${rule.title || rule.description || ''}`
    }
    if (!form.value.amount && rule.suggest_amount) {
      form.value.amount = Number(rule.suggest_amount)
    }
  }
}

const fetchDepartments = async () => {
  loadingDepartments.value = true
  try {
    const res = await apartmentStore.getApartmentsByFind('')
    departments.value = (res.data || []).map((d) => ({
      label: `${d.number} — ${d.owner?.name || 'Sin propietario'}`,
      value: d.id,
    }))
    filteredDepartments.value = departments.value
  } catch (err) {
    console.error(err)
    Notify.create({ color: 'negative', message: 'Error al cargar departamentos' })
  } finally {
    loadingDepartments.value = false
  }
}

const filterDepartments = (val, update) => {
  if (val === '') {
    update(() => {
      filteredDepartments.value = departments.value
    })
    return
  }
  update(() => {
    const needle = val.toLowerCase()
    filteredDepartments.value = departments.value.filter(
      (v) => v.label.toLowerCase().indexOf(needle) > -1
    )
  })
}

const fetchRules = async () => {
  loadingRules.value = true
  try {
    const res = await multaStore.getRules({ active: 1 })
    rules.value = res.data?.data || res.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loadingRules.value = false
  }
}

const fetchMulta = async () => {
  if (!isEdit.value) return
  loading.value = true
  try {
    const res = await multaStore.getMultaById(route.params.id)
    const data = res.data
    form.value = {
      departament_id: data.departament_id,
      rule_id: data.rule_id,
      description: data.description,
      incident_date: data.incident_date ? data.incident_date.substring(0, 10) : new Date().toISOString().substring(0, 10),
      amount: Number(data.amount) || 0,
      installments: Number(data.installments) || 1,
      start_month: data.start_month || (new Date().getMonth() + 1),
      start_year: data.start_year || new Date().getFullYear(),
      status: Number(data.status) || 1,
    }
  } catch {
    Notify.create({ color: 'negative', message: 'Error al cargar la multa' })
    router.push('/admin/multas')
  } finally {
    loading.value = false
  }
}

const submit = async () => {
  if (!form.value.departament_id || !form.value.description || !form.value.amount) {
    Notify.create({ color: 'negative', message: 'Completa todos los campos obligatorios (*)' })
    return
  }

  saving.value = true
  try {
    const payload = {
      departament_id: form.value.departament_id,
      rule_id: form.value.rule_id || null,
      description: form.value.description,
      incident_date: form.value.incident_date,
      amount: Number(form.value.amount),
      installments: Number(form.value.installments) || 1,
      start_month: Number(form.value.start_month),
      start_year: Number(form.value.start_year),
      status: Number(form.value.status) || 1,
    }

    if (isEdit.value) {
      await multaStore.updateMulta(route.params.id, payload)
      Notify.create({ color: 'positive', message: 'Multa actualizada y cuotas sincronizadas' })
    } else {
      await multaStore.createMulta(payload)
      Notify.create({ color: 'positive', message: 'Multa registrada y vinculada a la cuota mensual' })
    }

    router.push('/admin/multas')
  } catch (err) {
    Notify.create({ color: 'negative', message: err || 'Error al guardar la multa' })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchDepartments(), fetchRules()])
  if (isEdit.value) {
    await fetchMulta()
  }
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <!-- Header -->
    <div class="reserve-list-footer px-4 flex justify-between items-center md:w-full md:px-12" style="height: 10%;">
      <div class="flex items-center gap-2">
        <q-btn flat round dense icon="eva-arrow-back-outline" @click="router.push('/admin/multas')" />
        <span class="text-h6 text-bold text-gray-800">
          {{ isEdit ? 'Editar Multa' : 'Asignar Nueva Multa' }}
        </span>
      </div>
      <q-btn
        color="primary"
        unelevated
        no-caps
        style="border-radius: 0.5rem;"
        :loading="saving"
        @click="submit"
      >
        <q-icon name="eva-save-outline" size="1.2rem" class="mr-1" />
        {{ isEdit ? 'Guardar Cambios' : 'Registrar Multa' }}
      </q-btn>
    </div>

    <!-- Form Content -->
    <div style="height: 90%; overflow: auto;" class="px-4 py-6 md:px-12 flex justify-center">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
      </div>

      <div v-else class="w-full" style="max-width: 800px;">
        <q-card flat class="rounded-xl border border-gray-200 p-4 md:p-6 bg-white">
          <div class="row q-col-gutter-md">
            <!-- Departamento -->
            <div class="col-12 col-md-6">
              <label class="text-subtitle2 text-gray-700">Departamento sancionado *</label>
              <q-select
                v-model="form.departament_id"
                :options="filteredDepartments"
                dense
                outlined
                use-input
                input-debounce="200"
                emit-value
                map-options
                label="Seleccionar departamento..."
                class="mt-1"
                :loading="loadingDepartments"
                @filter="filterDepartments"
              />
            </div>

            <!-- Norma infringida opcional -->
            <div class="col-12 col-md-6">
              <label class="text-subtitle2 text-gray-700">Norma / Reglamento (opcional)</label>
              <q-select
                v-model="form.rule_id"
                :options="rules"
                option-label="title"
                option-value="id"
                dense
                outlined
                clearable
                emit-value
                map-options
                label="Vincular a norma..."
                class="mt-1"
                :loading="loadingRules"
                @update:model-value="onRuleSelected"
              >
                <template #option="scope">
                  <q-item v-bind="scope.itemProps">
                    <q-item-section>
                      <q-item-label>{{ scope.opt.code ? `[${scope.opt.code}] ` : '' }}{{ scope.opt.title }}</q-item-label>
                      <q-item-label caption v-if="scope.opt.suggest_amount">
                        Sanción sugerida: S/. {{ scope.opt.suggest_amount }}
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>

            <!-- Fecha del incidente -->
            <div class="col-12 col-md-6">
              <label class="text-subtitle2 text-gray-700">Fecha de la infracción *</label>
              <q-input
                v-model="form.incident_date"
                type="date"
                dense
                outlined
                class="mt-1"
              />
            </div>

            <!-- Monto total -->
            <div class="col-12 col-md-6">
              <label class="text-subtitle2 text-gray-700">Monto total de la multa (S/.) *</label>
              <q-input
                v-model.number="form.amount"
                type="number"
                min="0.01"
                step="0.01"
                dense
                outlined
                class="mt-1"
                prefix="S/."
              />
            </div>

            <!-- Descripción / Motivo -->
            <div class="col-12">
              <label class="text-subtitle2 text-gray-700">Descripción o motivo de la sanción *</label>
              <q-input
                v-model="form.description"
                type="textarea"
                rows="3"
                dense
                outlined
                class="mt-1"
                placeholder="Detalla la falta cometida, reincidencia, reporte de conserjería o evidencia..."
              />
            </div>

            <!-- Sección de aplicación en cuota mensual -->
            <div class="col-12 mt-4">
              <div class="p-4 bg-blue-50/50 rounded-lg border border-blue-200">
                <div class="text-subtitle1 text-bold text-primary mb-2 flex items-center gap-2">
                  <q-icon name="eva-calendar-outline" size="1.4rem" />
                  Cobro en cuota mensual
                </div>
                <div class="text-caption text-gray-600 mb-4">
                  Esta multa se sumará automáticamente a la cuota de mantenimiento del departamento (mismo comportamiento que los cargos extra).
                </div>

                <div class="row q-col-gutter-md">
                  <div class="col-12 col-md-4">
                    <label class="text-caption text-bold text-gray-700">Dividir en cuotas</label>
                    <q-input
                      v-model.number="form.installments"
                      type="number"
                      min="1"
                      dense
                      outlined
                      class="mt-1 bg-white"
                      suffix="cuota(s)"
                    />
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="text-caption text-bold text-gray-700">Mes de inicio</label>
                    <q-select
                      v-model="form.start_month"
                      :options="monthOptions"
                      dense
                      outlined
                      emit-value
                      map-options
                      class="mt-1 bg-white"
                    />
                  </div>

                  <div class="col-12 col-md-4">
                    <label class="text-caption text-bold text-gray-700">Año de inicio</label>
                    <q-input
                      v-model.number="form.start_year"
                      type="number"
                      min="2020"
                      dense
                      outlined
                      class="mt-1 bg-white"
                    />
                  </div>
                </div>

                <!-- Monto mensual calculado -->
                <div class="mt-4 pt-3 border-t border-blue-200 flex justify-between items-center text-subtitle2">
                  <span class="text-gray-700">Cargo mensual que se sumará a la cuota:</span>
                  <span class="text-bold text-primary text-h6">
                    S/. {{ monthlyAmount.toFixed(2) }} / mes
                  </span>
                </div>
              </div>
            </div>

            <!-- Estado si está editando -->
            <div class="col-12 col-md-6 mt-2" v-if="isEdit">
              <label class="text-subtitle2 text-gray-700">Estado de la multa</label>
              <q-select
                v-model="form.status"
                :options="statusOptions"
                dense
                outlined
                emit-value
                map-options
                class="mt-1"
              />
            </div>
          </div>
        </q-card>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.reserve-list-footer {
  border-bottom: 1px solid rgba(211, 211, 211, 0.4);
}
</style>

