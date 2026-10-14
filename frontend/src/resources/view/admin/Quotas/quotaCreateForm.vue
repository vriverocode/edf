<script setup>
import { ref, computed, onMounted } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import { useQuotaStore } from '@/services/store/quota.store'
import { useUserStore } from '@//services/store/users.store'
import { useDepartmentChargeStore } from '@/services/store/departmentCharge.store'
import { useAuthStore } from '@/services/store/auth.services'
import { useApartmentStore } from '@/services/store/apartment.store'

const router = useRouter()
const quotaStore = useQuotaStore()
const usersStore = useUserStore()
const chargeStore = useDepartmentChargeStore()
const authStore = useAuthStore()
const apartmentStore = useApartmentStore()
const saving = ref(false)
const loadingUsers = ref(false)

const users = ref([])
const filteredUsers = ref([])
const selectedUser = ref(null)
const selectedMonth = ref(new Date().getMonth() + 1)
const selectedYear = ref(new Date().getFullYear())

const deptRows = ref([])

const monthOptions = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
].map((m, i) => ({ label: m, value: i + 1 }))

const currentYear = new Date().getFullYear()
const yearOptions = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

const fetchUsers = async () => {
  loadingUsers.value = true
  try {
    const res = await usersStore.getUsersOptions()
    users.value = (res.data || []).map(u => {
      const unitNumbers = (u.units || []).map(x => x.number).join(', ')
      return {
        label: unitNumbers ? `${u.name} — ${unitNumbers}` : u.name,
        value: u.id,
        units: u.units || [],
      }
    })

    // Incluir al admin autenticado con sus unidades (getUsersOptions excluye roles admin)
    const me = authStore.user
    if (me?.id && [1, 8].includes(me.rol_id)) {
      const already = users.value.some(u => u.value === me.id)
      if (!already) {
        try {
          const deptRes = await apartmentStore.getDepartmentsByOwner(me.id)
          const units = (deptRes.data || []).map(d => ({
            id: d.id,
            number: d.number,
            relation: d._relation || 'owner',
          }))
          const unitNumbers = units.map(u => u.number).join(', ')
          users.value.unshift({
            label: unitNumbers ? `${me.name} — ${unitNumbers}` : `${me.name} (admin)`,
            value: me.id,
            units,
          })
        } catch {
          users.value.unshift({
            label: `${me.name} (admin)`,
            value: me.id,
            units: [],
          })
        }
      }
    }

    filteredUsers.value = users.value
  } catch (err) {
    console.log(err)
    Notify.create({ color: 'negative', message: 'Error al cargar usuarios' })
  } finally {
    loadingUsers.value = false
  }
}

const filterUsers = (val, update) => {
  if (val === '') {
    update(() => { filteredUsers.value = users.value })
    return
  }
  update(() => {
    const needle = val.toLowerCase()
    filteredUsers.value = users.value.filter(v =>
      v.label.toLowerCase().includes(needle) ||
      (v.units || []).some(u => String(u.number).toLowerCase().includes(needle))
    )
  })
}

const onUserSelected = (userId) => {
  deptRows.value = []
  if (!userId) return
  const user = users.value.find(u => u.value === userId)
  if (!user) return
  deptRows.value = (user.units || []).map(unit => ({
    departament_id: unit.id,
    number: unit.number,
    relation: unit.relation,
    total_amount: 0,
    water_amount: 0,
    charges: [],
  }))
}

const deptSubtotal = (row) => {
  const chargesTotal = row.charges.reduce((sum, c) => {
    const monthly = c.monthly_amount ?? (c.total_amount / c.installments)
    return sum + monthly
  }, 0)
  return (row.total_amount || 0) + chargesTotal
}

// Diálogo de gasto por departamento
const chargeDialog = ref(false)
const chargeDeptIndex = ref(null)
const chargeTab = ref('new')
const chargeForm = ref({ description: '', total_amount: 0, installments: 1 })
const existingCharges = ref([])
const loadingCharges = ref(false)
const filteredCharges = ref([])
const selectedExistingCharge = ref(null)

const fetchExistingCharges = async (departamentId) => {
  loadingCharges.value = true
  try {
    const res = await chargeStore.getDepartmentCharges({ departament_id: departamentId, status: 1, per_page: 999 })
    const list = res.data?.data || res.data || []
    existingCharges.value = list.map(c => ({
      label: `${c.description} — S/. ${Number(c.monthly_amount).toFixed(2)}/mes (${c.paid_installments}/${c.installments})`,
      value: c.id,
      id: c.id,
      description: c.description,
      total_amount: c.total_amount,
      monthly_amount: c.monthly_amount,
      installments: c.installments,
      start_month: c.start_month,
      start_year: c.start_year,
    }))
    filteredCharges.value = existingCharges.value
  } catch (err) {
    console.log(err)
    existingCharges.value = []
    filteredCharges.value = []
  } finally {
    loadingCharges.value = false
  }
}

const filterCharges = (val, update) => {
  if (val === '') {
    update(() => { filteredCharges.value = existingCharges.value })
    return
  }
  update(() => {
    const needle = val.toLowerCase()
    filteredCharges.value = existingCharges.value.filter(v => v.label.toLowerCase().includes(needle))
  })
}

const openChargeDialog = (deptIndex) => {
  chargeDeptIndex.value = deptIndex
  chargeForm.value = { description: '', total_amount: 0, installments: 1 }
  selectedExistingCharge.value = null
  chargeTab.value = 'new'
  chargeDialog.value = true
  const deptId = deptRows.value[deptIndex].departament_id
  fetchExistingCharges(deptId)
}

const addCharge = () => {
  if (chargeTab.value === 'existing') {
    if (!selectedExistingCharge.value) {
      Notify.create({ color: 'negative', message: 'Selecciona un cargo existente' })
      return
    }
    const charge = existingCharges.value.find(c => c.value === selectedExistingCharge.value)
    if (!charge) return
    const already = deptRows.value[chargeDeptIndex.value].charges.some(c => c.id === charge.id)
    if (already) {
      Notify.create({ color: 'warning', message: 'Este cargo ya está agregado' })
      return
    }
    deptRows.value[chargeDeptIndex.value].charges.push({
      id: charge.id,
      description: charge.description,
      total_amount: charge.total_amount,
      monthly_amount: charge.monthly_amount,
      installments: charge.installments,
      existing: true,
    })
  } else {
    if (!chargeForm.value.description || !chargeForm.value.total_amount || chargeForm.value.installments < 1) {
      Notify.create({ color: 'negative', message: 'Completa los campos del gasto' })
      return
    }
    deptRows.value[chargeDeptIndex.value].charges.push({ ...chargeForm.value })
  }
  chargeDialog.value = false
  chargeDeptIndex.value = null
}

const removeCharge = (deptIndex, chargeIndex) => {
  deptRows.value[deptIndex].charges.splice(chargeIndex, 1)
}

const submit = async () => {
  if (!selectedUser.value) {
    Notify.create({ color: 'negative', message: 'Selecciona un usuario' })
    return
  }
  if (!selectedMonth.value || !selectedYear.value) {
    Notify.create({ color: 'negative', message: 'Selecciona mes y año' })
    return
  }
  const withAmount = deptRows.value.filter(r => r.total_amount > 0)
  if (!withAmount.length) {
    Notify.create({ color: 'negative', message: 'Ingresa al menos un monto total en un departamento' })
    return
  }

  saving.value = true
  try {
    const payload = {
      user_id: selectedUser.value,
      month: selectedMonth.value,
      year: selectedYear.value,
      departments: deptRows.value.map(r => ({
        departament_id: r.departament_id,
        total_amount: r.total_amount,
        water_amount: r.water_amount,
        charges: r.charges,
      })),
    }
    const res = await quotaStore.createQuotasBatch(payload)
    const { created, skipped } = res.data
    if (skipped && skipped.length) {
      const skippedNums = skipped.map(s => s.number || s.departament_id).join(', ')
      const reasons = skipped.map(s => `${s.number || s.departament_id}: ${s.reason}`).join('\n')
      Notify.create({
        color: 'negative',
        message: created > 0
          ? `${created} cuota(s) creada(s). Ya existían cuotas en: ${skippedNums}`
          : `Ya existen cuotas para: ${skippedNums}`,
        caption: reasons,
        timeout: 8000,
      })
      if (created === 0) {
        return
      }
    } else {
      Notify.create({ color: 'positive', message: `${created} cuota(s) creada(s) correctamente` })
    }
    router.push('/admin/quotas/maintenance/list')
  } catch (err) {
    console.log(err)
    Notify.create({ color: 'negative', message: (typeof err === 'string' ? err : err?.error) || 'Error al crear cuotas' })
  } finally {
    saving.value = false
  }
}

onMounted(fetchUsers)
</script>

<template>
  <div class="md:px-32 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Crear cuotas manualmente
    </div>

    <div class="q-mt-md row md:px-20">
      <div class="col-12 md:px-2">
        <div class="text-subtitle2 text-grey-9 q-mb-xs">Usuario *</div>
        <q-select
          v-model="selectedUser"
          :options="filteredUsers"
          emit-value
          map-options
          dense
          borderless
          class="form__inputsR"
          use-input
          input-debounce="300"
          @filter="filterUsers"
          :loading="loadingUsers"
          @update:model-value="onUserSelected"
          :rules="[(val) => !!val || 'Requerido']"
        />
      </div>
      <div class="col-12 col-md-6 mb-5 md:px-2 pt-5">
        <div class="text-subtitle2 text-grey-9 q-mb-xs">Mes *</div>
        <q-select
          v-model="selectedMonth"
          :options="monthOptions"
          emit-value
          map-options
          dense
          borderless
          class="form__inputsR"
        />
      </div>
      <div class="col-12 col-md-6 mb-5 md:px-2 pt-5">
        <div class="text-subtitle2 text-grey-9 q-mb-xs">Año *</div>
        <q-select
          v-model="selectedYear"
          :options="yearOptions"
          dense
          borderless
          class="form__inputsR"
        />
      </div>
    </div>

    <div v-if="deptRows.length" class="q-mt-lg md:px-20 ">
      <div class="text-h6 text-bold q-mb-sm">Departamentos</div>
      <q-card flat bordered class="q-mb-md py-5" style="border-radius: 0.8rem;" v-for="(row, deptIndex) in deptRows" :key="row.departament_id">
        <q-card-section class="row items-start q-pa-sm">
          <div class="col-12 col-md-1 md:pt-4">
            <div class="text-caption text-grey-7">Unidad</div>
            <div class="text-bold">{{ row.number }}</div>
            <div class="text-caption text-grey-6">{{ row.relation === 'owner' ? 'Propietario' : 'Inquilino' }}</div>
          </div>
          <div class="col-12 col-md-4 q-px-sm">
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Monto total (S/.) *</div>
            <q-input
              v-model.number="row.total_amount"
              type="number"
              dense
              borderless
              class="form__inputsR"
              min="0"
            />
            <div
              class="mt-2 text-primary cursor-pointer"
              @click="openChargeDialog(deptIndex)"
              style="text-decoration: underline; "
            >
             Añadir gasto
          </div>
          </div>
          <div class="col-12 col-md-4 q-px-sm">
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Monto agua (S/.)</div>
            <q-input
              v-model.number="row.water_amount"
              type="number"
              dense
              borderless
              class="form__inputsR"
              min="0"
            />
          </div>
          <div class="col-12 col-md-3 q-px-sm md:pt-6">
            <div class="text-caption text-grey-7">Subtotal</div>
            <div class="text-bold text-primary">S/. {{ deptSubtotal(row).toFixed(2) }}</div>
          </div>

          <div v-if="row.charges.length" class="col-12 q-mt-sm">
            <q-separator spaced />
            <div
              v-for="(charge, chargeIndex) in row.charges"
              :key="chargeIndex"
              class="row items-center q-pa-xs"
            >
              <div class="col-6 text-caption">
                {{ charge.description }} — {{ charge.installments }} cuota(s) — S/. {{ (charge.monthly_amount ?? (charge.total_amount / charge.installments)).toFixed(2) }}/mes
                <q-badge v-if="charge.existing" color="info" label="existente" class="q-ml-xs" />
              </div>
              <div class="col-6 flex justify-end">
                <q-btn
                  flat
                  dense
                  round
                  color="negative"
                  icon="eva-close-outline"
                  size="sm"
                  @click="removeCharge(deptIndex, chargeIndex)"
                />
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <div class="flex justify-end q-mt-md">
        <q-btn flat color="grey-7" label="Cancelar" class="q-mr-md" @click="router.push('/admin/quotas/maintenance/list')" />
        <q-btn
          color="primary"
          unelevated
          :loading="saving"
          style="border-radius: 0.5rem; min-width: 150px;"
          @click="submit"
        >
          <div class="flex items-center py-1">
            <div class="q-pt-xs text-bold pl-1">Crear cuotas</div>
          </div>
        </q-btn>
      </div>
    </div>

    <div v-else-if="selectedUser" class="text-center text-grey-7 q-mt-xl">
      Este usuario no tiene departamentos registrados
    </div>

    <q-dialog v-model="chargeDialog" persistent>
      <q-card style="min-width: 380px">
        <q-card-section class="text-h6 text-bold q-pb-sm">
          Agregar gasto
        </q-card-section>
        <q-tabs
          v-model="chargeTab"
          dense
          class="text-grey-7 q-px-md"
          active-color="primary"
          indicator-color="primary"
          align="justify"
          narrow-indicator
        >
          <q-tab name="new" label="Nuevo" />
          <q-tab name="existing" label="Existente" />
        </q-tabs>
        <q-separator />
        <q-tab-panels v-model="chargeTab" animated>
          <q-tab-panel name="new">
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Descripción *</div>
            <q-input
              v-model="chargeForm.description"
              dense
              borderless
              class="form__inputsR q-mb-md"
              placeholder="Ej: Daño en equipo"
            />
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Monto total (S/.) *</div>
            <q-input
              v-model.number="chargeForm.total_amount"
              type="number"
              dense
              borderless
              class="form__inputsR q-mb-md"
              min="0"
            />
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Cuotas *</div>
            <q-input
              v-model.number="chargeForm.installments"
              type="number"
              dense
              borderless
              class="form__inputsR"
              min="1"
            />
          </q-tab-panel>
          <q-tab-panel name="existing">
            <div class="text-subtitle2 text-grey-9 q-mb-xs">Buscar cargo existente</div>
            <q-select
              v-model="selectedExistingCharge"
              :options="filteredCharges"
              emit-value
              map-options
              dense
              borderless
              class="form__inputsR"
              use-input
              input-debounce="300"
              @filter="filterCharges"
              :loading="loadingCharges"
              placeholder="Buscar por descripción..."
            >
              <template v-slot:no-option>
                <q-item>
                  <q-item-section class="text-grey">
                    {{ loadingCharges ? 'Cargando...' : 'No hay cargos activos para este departamento' }}
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </q-tab-panel>
        </q-tab-panels>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-7" v-close-popup />
          <q-btn label="Agregar" color="primary" unelevated @click="addCharge" />
        </q-card-actions>
      </q-card>
    </q-dialog>
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
