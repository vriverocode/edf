<script setup>
import { ref, onMounted, computed } from 'vue';
import { Notify } from 'quasar'
import { useQuotaStore } from '@/services/store/quota.store';
import { usePayStore } from '@/services/store/pay.store';
import { useRouter } from 'vue-router';
import moment from 'moment';
import appIcons from '@/assets/icons';
import cuotas from '@/assets/img/menu/cuotas.png'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const quotas = ref([]);
const loading = ref(true);
const quotaStore = useQuotaStore();
const payStore = usePayStore();
const router = useRouter();
const dialog = ref(false);
const creditItems = ref([]);
const filters = ref({
  status: 4,
  quota_method: '',
  type: '',
  date_from: '',
  date_to: '',
  sort_by: 'created_at',
  sort_dir: 'desc'
});

const getQuotas = () => {
  loading.value = true;
  quotaStore.getQuotasByUser()
    .then((response) => {
      if (response.code !== 200) throw response;
      quotas.value = response.data;
      fetchCreditBalances();
    })
    .catch((response) => {
      console.error(response);
    })
    .finally(() => {
      loading.value = false;
    });
}

const fetchCreditBalances = async () => {
  const deptIds = new Set();
  quotas.value.forEach(q => {
    if (q.details) {
      q.details.forEach(d => {
        if (d.departament?.id) deptIds.add(d.departament.id);
      });
    } else if (q.departament?.id) {
      deptIds.add(q.departament.id);
    }
  });
  if (deptIds.size === 0) return;
  try {
    const res = await payStore.getCreditBalanceForDepartments([...deptIds]);
    if (res?.code === 200 && res.data?.detail) {
      creditItems.value = res.data.items || [];
    }
  } catch {}
}

const quotaYear = (quota) => {
  if (quota.year != null && quota.year !== '') return Number(quota.year);
  if (quota.due_date) return new Date(quota.due_date).getFullYear();
  return moment().year();
};

const creditForDeptPeriod = (deptId, month, year) => {
  // El backend ya devuelve los saldos; aqui solo se atribuyen al mes de la
  // cuota. Los legacy con mes 0 (Flexible) ya no aplican a ninguna cuota.
  return creditItems.value.reduce((sum, item) => {
    if (item.departament_id !== deptId) return sum;
    if (item.balance <= 0) return sum;
    const m = Number(item.applicable_month || 0);
    if (m === 0) return sum;
    const y = item.applicable_year;
    if (m === month && (y == null || Number(y) === year)) {
      return sum + item.balance;
    }
    return sum;
  }, 0);
}

const getCreditForQuota = (quota) => {
  const month = Number(quota.month);
  const year = quotaYear(quota);

  const deptId = quota.departament?.id;
  const detailIds = quota.details
    ? quota.details.map(d => d.departament?.id).filter(Boolean)
    : (deptId ? [deptId] : []);

  return detailIds.reduce((sum, id) => sum + creditForDeptPeriod(id, month, year), 0);
}

const quotaAmountAfterCredit = (quota) => {
  const credit = getCreditForQuota(quota);
  return Math.max(0, Number(quota.amount) - credit);
}

const resolvePayId = (quota) => {
  if (quota.pay) return quota.pay
  const pays = quota.details && quota.details[0] && quota.details[0].pays
    ? quota.details[0].pays.filter(p => ![0].includes(p.status))
    : []
  const preferred = pays.find(p => p.status === 1)
    ?? pays.find(p => p.status === 2)
    ?? pays[0]
  return preferred ? preferred.id : null
}

const goTo = (quota) => {
  if (quota.status == 2 || quota.status == 3) {
    const payId = resolvePayId(quota)
    if (payId) {
      router.push(`/client/pay/quotas/view/${payId}`)
    }
    return
  }
  if (isBlockedByOlderMonth(quota)) {
    Notify.create({
      color: 'warning',
      icon: 'eva-alert-circle-outline',
      message: `Primero debes pagar la cuota de ${oldestOwedLabel.value}.`,
      timeout: 3000
    })
    return
  }
  // Solo cuotas realmente pendientes (status 1): un grupo consolidado puede
  // mezclar unidades ya pagadas y enviarlas aqui provocaria un 409 en
  // storePay (denyNonPending).
  const ids = quota.details
    ? quota.details.filter(d => Number(d.status) === 1).map(d => d.id)
    : [quota.id]
  if (!ids.length) return
  router.push({
    path: `/client/quota/pay/${ids[0]}`,
    query: ids.length > 0 ? { quota_ids: ids.join(',') } : {}
  })
}

const goToDetail = (quota) => {
  const id = quota.details?.[0]?.id ?? null
  if (id) router.push({ name: 'viewQuota', params: { id } })
}

const downloadingId = ref(null)

const quotaDetailIds = (quota) => {
  const details = Array.isArray(quota.details) && quota.details.length ? quota.details : [quota]
  return details.map(d => d.id).filter(Boolean)
}

const downloadQuotaPdf = async (quota) => {
  const ids = quotaDetailIds(quota)
  if (!ids.length) return
  downloadingId.value = quota.id
  try {
    await quotaStore.downloadQuotaStatement(ids)
  } catch (message) {
    Notify.create({
      color: 'negative',
      icon: 'eva-alert-circle-outline',
      message: typeof message === 'string' ? message : 'No se pudo descargar el estado de cuenta',
      timeout: 3000
    })
  } finally {
    downloadingId.value = null
  }
}

// Id de la cuota que tiene lectura de agua vinculada (para el detalle de agua)
const waterQuotaId = (quota) => {
  const details = Array.isArray(quota.details) ? quota.details : []
  const withReading = details.find(d => d.water_reading_id)
  if (withReading) return withReading.id
  return quota.water_reading_id ? (quota.details?.[0]?.id ?? quota.id) : null
}

const goToWaterReading = (quota) => {
  const id = waterQuotaId(quota)
  if (id) router.push(`/client/quota/water-detail/${id}`)
}

const showDialog = () => {
  dialog.value = true;
}

const updateFilters = (newFilters) => {
  filters.value = { ...newFilters };
  getQuotas();
}

const closeModal = () => {
  dialog.value = false;
}

const getMonthName = (monthNumber) => {
  const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
  return months[monthNumber - 1] || '';
}

const isMonthlyQuota = (quota) => Number(quota.month) >= 1 && Number(quota.month) <= 12

// Comparable como numero: 202609 -> 202609. Se usa due_date como respaldo del
// anio porque las filas antigas pueden venir sin year.
const periodKey = (quota) => {
  const month = Number(quota.month)
  const year = Number(quota.year) || new Date(quota.due_date).getFullYear()
  return year * 100 + month
}

// Status 2 (pago en revision) tambien cuenta como deuda: el dinero aun no esta
// saldado, asi que el mes siguiente debe quedar bloqueado igual que en backend.
const owedPeriods = computed(() =>
  quotas.value.filter(q => isMonthlyQuota(q) && (Number(q.status) === 1 || Number(q.status) === 2))
)

const oldestOwedPeriod = computed(() => {
  if (!owedPeriods.value.length) return null
  return owedPeriods.value.reduce((oldest, q) => (periodKey(q) < periodKey(oldest) ? q : oldest))
})

const isBlockedByOlderMonth = (quota) => {
  if (Number(quota.status) !== 1) return false
  if (!isMonthlyQuota(quota)) return false
  if (!oldestOwedPeriod.value) return false
  return periodKey(quota) > periodKey(oldestOwedPeriod.value)
}

const oldestOwedLabel = computed(() => {
  if (!oldestOwedPeriod.value) return ''
  return getTitleQuota(oldestOwedPeriod.value).replace('Mensualidad: ', '')
})

// ── Selección múltiple (pagar varios meses en un solo pago) ──────────────────
// Regla: la selección debe ser contigua desde el mes deudor más antiguo. Es el
// mismo criterio que aplica el backend en QuotaPaymentEligibilityService
// (findOlderDebt usa la due_date mínima del set como frontera).
const multiMode = ref(false)
const selectedGroupIds = ref([])

const isPayableMonthly = (quota) =>
  isMonthlyQuota(quota) && Number(quota.status) === 1

const payableMonthlyGroups = computed(() =>
  quotas.value.filter(isPayableMonthly)
)

const multiButtonVisible = computed(() => payableMonthlyGroups.value.length >= 2)

const toggleMultiMode = () => {
  multiMode.value = !multiMode.value
  selectedGroupIds.value = []
}

// Grupo mensual deudor más antiguo que impide seleccionar `group`: cualquier
// deuda (status 1 o 2) anterior al grupo y aún no seleccionada. Un status 2
// nunca es seleccionable (pago en revisión), por eso bloquea a los posteriores.
const blockingGroupFor = (group) => {
  if (!group || !isMonthlyQuota(group)) return null
  const key = periodKey(group)
  const blockers = owedPeriods.value.filter(
    q => periodKey(q) < key && !selectedGroupIds.value.includes(q.id)
  )
  if (!blockers.length) return null
  return blockers.reduce((oldest, q) => (periodKey(q) < periodKey(oldest) ? q : oldest))
}

const canSelectGroup = (group) => !blockingGroupFor(group)

const isGroupSelected = (group) => selectedGroupIds.value.includes(group.id)

const shortPeriodLabel = (group) =>
  getTitleQuota(group).replace('Mensualidad: ', '')

const toggleMultiSelect = (group) => {
  if (!isPayableMonthly(group)) return
  if (!canSelectGroup(group)) {
    const blocker = blockingGroupFor(group)
    Notify.create({
      color: 'warning',
      icon: 'eva-lock-outline',
      message: blocker?.status == 2
        ? `La cuota de ${shortPeriodLabel(blocker)} está en revisión; espera su aprobación.`
        : `Primero selecciona la cuota de ${shortPeriodLabel(blocker)}.`,
      timeout: 3000
    })
    return
  }
  const idx = selectedGroupIds.value.indexOf(group.id)
  if (idx >= 0) {
    selectedGroupIds.value.splice(idx, 1)
  } else {
    selectedGroupIds.value.push(group.id)
  }
}

const selectedGroups = computed(() =>
  quotas.value
    .filter(q => selectedGroupIds.value.includes(q.id))
    .sort((a, b) => periodKey(a) - periodKey(b))
)

// Ids de las cuotas realmente pendientes (status 1) de los grupos marcados,
// ordenados del más antiguo al más reciente.
const selectedPendingQuotaIds = computed(() =>
  selectedGroups.value.flatMap(g =>
    g.details
      ? g.details.filter(d => Number(d.status) === 1).map(d => d.id)
      : [g.id]
  )
)

// El saldo a favor se aplica sobre cada mes seleccionado, con tope por
// depto y periodo (mismo criterio que CreditService en el backend): un
// crédito de junio descuenta la cuota de junio aunque se paguen más meses.
const multiCreditAllocations = computed(() => {
  const rows = []
  selectedGroups.value.forEach(group => {
    if (!isMonthlyQuota(group)) return
    const key = periodKey(group)
    const month = key % 100
    const year = Math.floor(key / 100)
    // Solo las cuotas realmente pendientes entran al pago y al tope.
    const details = group.details
      ? group.details.filter(d => Number(d.status) === 1)
      : [group]
    const byDept = {}
    details.forEach(d => {
      const deptId = d.departament?.id ?? group.departament?.id
      if (!deptId) return
      byDept[deptId] = (byDept[deptId] || 0) + (Number(d.amount) || 0)
    })
    let applied = 0
    Object.entries(byDept).forEach(([deptId, amount]) => {
      applied += Math.min(creditForDeptPeriod(Number(deptId), month, year), amount)
    })
    applied = Math.round(applied * 100) / 100
    if (applied > 0) {
      rows.push({ group, label: shortPeriodLabel(group), applied })
    }
  })
  return rows
})

const multiCreditToApply = computed(() => {
  return Math.round(multiCreditAllocations.value.reduce((s, r) => s + r.applied, 0) * 100) / 100
})

const multiCreditLabels = computed(() =>
  multiCreditAllocations.value.map(r => r.label).join(', ')
)

const multiTotal = computed(() => {
  const sum = selectedGroups.value.reduce(
    (s, q) => s + (Number(q.amount) || 0),
    0
  )
  return Math.max(0, Math.round((sum - multiCreditToApply.value) * 100) / 100)
})

const paySelectedMonths = () => {
  const ids = selectedPendingQuotaIds.value
  if (!ids.length) {
    Notify.create({
      color: 'warning',
      icon: 'eva-alert-circle-outline',
      message: 'Selecciona al menos un mes.'
    })
    return
  }
  multiMode.value = false
  selectedGroupIds.value = []
  router.push({
    path: `/client/quota/pay/${ids[0]}`,
    query: { quota_ids: ids.join(',') }
  })
}

const getTitleQuota = (quota) => {
  // month = 0 corresponde a cargos anuales importados ("Cuota Periodo 2025"),
  // no a un mes.
  if (!isMonthlyQuota(quota)) {
    const year = Number(quota.year) || new Date(quota.due_date).getFullYear()
    return `Cuota Periodo ${year}`
  }
  // Utilizamos el mes que viene en la agrupación
  return 'Mensualidad: ' + getMonthName(quota.month);
}

const hasTenantPays = (quota) => {  
  if (!quota.details || !quota.details.length) return false
  
  return quota.details.some(d => d.departament?.tenant_pays_quota == true)
}

const getStatusInfo = (status) => {
  if (status === 1) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente' };
  }
  if (status === 2) {
    return { color: 'warning', icon: 'eva-alert-circle-outline', label: 'Pendiente de aprob.' };
  }
  if (status === 3) {
    return { color: 'positive', icon: 'eva-checkmark-circle-2-outline', label: 'Pagado' };
  }
  return { color: 'negative', icon: 'eva-checkmark-circle-2-outline', label: 'Rechazado' };
}

const formatDate = (date) => {
  if (!date) return '';
  return moment(date).format('DD MMM YYYY');
}

onMounted(() => {
  getQuotas();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="h-full" style="overflow: auto;">
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 py-6 md:px-28">
        <div v-if="quotas.length > 0" class="space-y-5 md:px-5" :class="multiMode && selectedGroups.length ? 'pb-28' : ''">
          <div v-if="multiButtonVisible" class="flex justify-end">
            <q-btn
              :color="multiMode ? 'grey-8' : 'primary'"
              :label="multiMode ? 'Cancelar selección' : 'Pagar varios meses'"
              no-caps
              unelevated
              :icon="multiMode ? 'eva-close-outline' : 'eva-layers-outline'"
              class="text-caption"
              @click="toggleMultiMode"
            />
          </div>
          <div v-for="quota in quotas" :key="quota.id"
            class="bg-white rounded-xl shadow-md overflow-hidden md:mb-5"
            :class="isGroupSelected(quota) ? 'shadow-lg' : ''"
            style="position: relative"
            :style="isGroupSelected(quota) ? 'border: 2px solid #80cbc4' : 'border: 1px solid lightgrey'">

            <div class="px-4 pb-2 pt-2 md:pt-4 cursor-pointer" @click="goToDetail(quota)">
              <div class="flex justify-between items-start mb-0 pb-1" style="border-bottom: 1px dashed #111827;">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-1">
                    {{ getTitleQuota(quota) }}
                    <span v-if="hasTenantPays(quota)"
                      class="ml-2 inline-flex items-center bg-amber-100 text-amber-800 text-xs font-semibold px-2 py-0.5 rounded-md">
                      <q-icon name="eva-person-outline" size="0.85rem" class="mr-0.5" />
                      Inquilino paga
                    </span>
                    <span v-if="quota.created_at && moment(quota.created_at).isAfter(moment().subtract(7, 'days'))"
                      class="absolute top-2 right-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded-md">
                      Nuevo
                    </span>
                  </h3>
                  <div class="text-xs text-gray-500 mb-2">{{ quota.description }}</div>
                </div>
              </div>

              <div class="space-y-2 pt-3">
                <div class="row items-center ">
                  <div class="flex items-center text-sm text-gray-700 col-5 col-md-4">
                    <svg class="w-5 h-5 mr-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                      </path>
                    </svg>
                    <div class="flex">
                      <span class="font-medium text-base flex items-center" >
                        <div v-if="getCreditForQuota(quota) > 0" class="text-sm text-gray-400 line-through mr-2">
                          S/. {{ Number(quota.amount).toFixed(2) }}
                        </div>
                       S/. {{ quotaAmountAfterCredit(quota).toFixed(2) }}
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center text-sm text-gray-700 col-7 col-md-8 justify-end md:justify-start">
                    <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                      </path>
                    </svg>
                    <span class="font-medium">Límite: {{ formatDate(quota.due_date) }}</span>
                  </div>
                  <div v-if="getCreditForQuota(quota) > 0" class="text-xs mt-1">
                    <q-badge color="green" class="text-white px-2 py-1">
                      <q-icon name="eva-checkmark-circle-2-outline" size="0.85rem" class="mr-1" />
                      Saldo a favor: S/. {{ getCreditForQuota(quota).toFixed(2) }}
                    </q-badge>
                  </div>
                </div>
                <!-- Lectura de agua vinculada a la cuota -->
                <div v-if="waterQuotaId(quota)" class="mt-1">
                  <q-btn dense flat no-caps icon="eva-droplet-outline" color="info" label="Ver lectura de agua"
                    class="text-caption" @click.stop="goToWaterReading(quota)" />
                </div>
                <!-- Estado de cuenta en PDF (cualquier estado, combinado por mes) -->
                <div class="mt-1">
                  <q-btn dense flat no-caps icon="eva-download-outline" color="primary"
                    label="Descargar estado de cuenta" class="text-caption"
                    :loading="downloadingId === quota.id" @click.stop="downloadQuotaPdf(quota)" />
                </div>
              </div>
            </div>
            <!-- Modo selección múltiple: marcar el mes para el pago consolidado -->
            <div
              v-if="multiMode && isPayableMonthly(quota)"
              class="px-4 py-2 md:py-3 border-t"
              :class="canSelectGroup(quota) ? 'bg-primary cursor-pointer' : 'bg-grey-4'"
              @click="toggleMultiSelect(quota)"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <q-icon
                    :name="isGroupSelected(quota) ? 'eva-checkmark-square-2' : 'eva-square-outline'"
                    :color="canSelectGroup(quota) ? 'white' : 'grey-6'"
                    size="1.7rem"
                  />
                  <span class="ml-2 text-sm font-medium"
                    :class="canSelectGroup(quota) ? 'text-white' : 'text-grey-9'">
                    {{ isGroupSelected(quota) ? 'Seleccionado' : 'Seleccionar mes' }}
                  </span>
                </div>
                <span class="font-medium text-base"
                  :class="canSelectGroup(quota) ? 'text-white' : 'text-grey-9'">
                  S/. {{ Number(quota.amount).toFixed(2) }}
                </span>
              </div>
              <div v-if="!canSelectGroup(quota)" class="flex items-center mt-1 text-xs text-grey-8">
                <q-icon name="eva-lock-outline" size="1rem" class="mr-1" />
                <span v-if="blockingGroupFor(quota)?.status == 2">
                  En revisión: espera la aprobación de {{ shortPeriodLabel(blockingGroupFor(quota)) }}
                </span>
                <span v-else>
                  Primero selecciona {{ shortPeriodLabel(blockingGroupFor(quota)) }}
                </span>
              </div>
            </div>
            <!-- Flujo normal: candado de mes más antiguo o botón Pagar -->
            <template v-else>
              <div v-if="isBlockedByOlderMonth(quota)"
                class="px-4 py-2 md:py-3 border-t bg-grey-4">
                <div class="flex justify-center items-center">
                  <div class="flex items-center">
                    <q-icon name="eva-lock-outline" color="grey-8" size="1.5rem" />
                    <span class="ml-1 text-sm font-medium text-grey-9">
                      Paga primero {{ oldestOwedLabel }}
                    </span>
                  </div>
                </div>
              </div>
              <div v-else class="px-4 py-2 md:py-3 border-t cursor-pointer" :class="`bg-${getStatusInfo(quota.status).color}`"
                @click="goTo(quota)">
                <div class="flex justify-center items-center">
                  <div class="flex items-center">
                    <q-icon :name="getStatusInfo(quota.status).icon" color="white" size="1.5rem" />
                    <span class="ml-1 text-sm font-medium text-white mr-2">
                      <span class="" v-if="quota.status ==1">Pagar</span> {{ getStatusInfo(quota.status).label }}
                    </span>
                    <span  class="font-medium text-base text-white">
                      S/. {{ quotaAmountAfterCredit(quota).toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
         <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <img :src="cuotas" class="md:w-auto h-3/5"/>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">¡No tienes cuotas pendientes! 🎉</h3>
          <p class="text-gray-600 text-center mb-6"> Estás al día con tus pagos. Gracias por contribuir al mantenimiento y buen funcionamiento del edificio.</p>
        </div>

        <!-- Barra inferior: resumen del pago consolidado multi-mes -->
        <div
          v-if="multiMode && selectedGroups.length"
          class="fixed bottom-0 inset-x-0 z-20 px-4 py-3 bg-primary text-white flex items-center justify-between shadow-2xl"
          style="border-top-left-radius: 1rem; border-top-right-radius: 1rem;"
        >
          <div>
            <div class="text-sm font-semibold">
              {{ selectedGroups.length }} {{ selectedGroups.length === 1 ? 'mes seleccionado' : 'meses seleccionados' }}
            </div>
            <div v-if="multiCreditToApply > 0" class="text-xs" style="opacity: 0.9;">
              Saldo a favor: -S/. {{ multiCreditToApply.toFixed(2) }}
              <template v-if="multiCreditLabels">({{ multiCreditLabels }})</template>
            </div>
          </div>
          <div class="flex items-center">
            <div class="text-h6 text-bold text-white mr-3">S/. {{ multiTotal.toFixed(2) }}</div>
            <q-btn
              color="white"
              text-color="primary"
              label="Continuar"
              no-caps
              unelevated
              @click="paySelectedMonths"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Estilos adicionales si es necesario */
</style>