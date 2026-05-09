<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/services/store/auth.services'
import { useMonthlyBillsStore } from '@/services/store/monthlyBills.store'

const emit = defineEmits(['offset'])

const { user } = storeToRefs(useAuthStore())
const route = useRoute()
const router = useRouter()
const monthlyBillsStore = useMonthlyBillsStore()

const loading = ref(true)
const exists = ref(false)
const dismissed = ref(false)

const isAdmin = computed(() => (user.value?.rol_id ?? 0) === 1)

const isFirstDayOfMonth = computed(() => {
  const now = new Date()
  return now.getDate() === 1
})

const month = computed(() => new Date().getMonth() + 1)
const year = computed(() => new Date().getFullYear())
const monthName = computed(() => {
  const names = [
    'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
  ]
  return names[month.value - 1] || ''
})

const storageDismissKey = computed(
  () => `budget_reminder_dismissed_${year.value}_${month.value}`
)

const shouldCheck = computed(
  () => isAdmin.value && isFirstDayOfMonth.value
)

const showBanner = computed(
  () =>
    shouldCheck.value &&
    !loading.value &&
    !exists.value &&
    !dismissed.value
)

watch(
  showBanner,
  (v) => {
    emit('offset', v ? 52 : 0)
  },
  { immediate: true }
)

async function checkBudget() {
  dismissed.value = sessionStorage.getItem(storageDismissKey.value) === '1'

  if (!shouldCheck.value) {
    loading.value = false
    return
  }

  loading.value = true
  try {
    const res = await monthlyBillsStore.checkBudgetExistsForPeriod(month.value, year.value)
    if (res.code !== 200) throw res
    exists.value = !!res.data?.exists
  } catch (e) {
    console.error(e)
    exists.value = false
  } finally {
    loading.value = false
  }
}

function dismiss() {
  sessionStorage.setItem(storageDismissKey.value, '1')
  dismissed.value = true
}

function goToForm() {
  router.push('/admin/monthly_bills/form/add')
}

onMounted(() => {
  checkBudget()
})

watch(
  () => route.fullPath,
  () => {
    checkBudget()
  }
)
</script>

<template>
  <teleport to="body">
    <transition name="fade">
      <div
        v-if="showBanner"
        class="budget-reminder-banner"
        role="alert"
      >
        <div class="budget-reminder-banner__inner row items-center justify-between q-px-md q-py-sm">
          <div class="col">
            <div class="text-white text-weight-medium text-caption md:text-body2">
              Es el primer día del mes: recuerda cargar el <strong>presupuesto mensual</strong>
              (gastos y mantenimiento) para {{ monthName }} {{ year }}.
            </div>
          </div>
          <div class="col-auto row items-center q-gutter-sm no-wrap">
            <q-btn
              unelevated
              color="white"
              text-color="grey-10"
              dense
              no-caps
              class="text-caption"
              label="Cargar presupuesto"
              @click="goToForm"
            />
            <q-btn
              flat
              dense
              round
              color="white"
              icon="close"
              aria-label="Cerrar aviso"
              @click="dismiss"
            />
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<style scoped>
.budget-reminder-banner {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10050;
  padding-top: env(safe-area-inset-top, 0);
  background: linear-gradient(90deg, #d97706 0%, #b45309 100%);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.budget-reminder-banner__inner {
  max-width: 1200px;
  margin: 0 auto;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
