<script setup>
import { onMounted, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import { useQuotaStore } from '@/services/store/quota.store';
import { useExpenseStore } from '@/services/store/expense.store';
import { useReserveStore } from '@/services/store/reserve.store';
import comunArea from '@/assets/img/menu/areas-comunes.png'
import booking from '@/assets/img/menu/reservas.png'
import news from '@/assets/img/menu/noticias.png'
import events from '@/assets/img/menu/eventos-admin.png'
import report from '@/assets/img/menu/reports.png'
import maintenance from '@/assets/img/menu/worker.png'

const router = useRouter()
const userStore = useUserStore()
const quotaStore = useQuotaStore()
const expenseStore = useExpenseStore()
const reserveStore = useReserveStore()

const pendings = ref({})
const loading = ref(true)
const alertMessages = ref([])
const showTicker = ref(true)

const now = new Date()
const month = now.getMonth() + 1
const year = now.getFullYear()

const menu = [
  { title: 'Areas comunes', icon: comunArea, subtitle: 'Gestiona las areas comunes', link: '/admin/comun-area/list', badgeKey: '' },
  { title: 'Reservas', icon: booking, subtitle: 'Informacion de reservas', link: '/reserves', badgeKey: 'pendingReserves' },
  { title: 'Noticias', icon: news, subtitle: 'Envia información sobre: eventos, servicio, etc', link: '/admin/notices', badgeKey: 'pendingNotices' },
  { title: 'Eventos', icon: events, subtitle: 'Modulo de gestion de eventos', link: '/admin/events', badgeKey: 'pendingEvents' },
  { title: 'Mantenimientos', icon: maintenance, subtitle: 'Programa y consulta mantenimientos', link: '/admin/maintenances', badgeKey: '' },
  { title: 'Reportes', icon: report, subtitle: 'Reportes', link: '/admin/reports', badgeKey: '' },
]

const goTo = (url) => router.push(url)

const badgeCount = (key) => {
  if (!key || !pendings.value[key]) return 0
  return pendings.value[key] || 0
}

const fetchDashboardData = async () => {
  loading.value = true
  try {
    const res = await userStore.getAllPendingsForAdmin()
    if (res?.code === 200) {
      pendings.value = res.data || {}
    }
    const alerts = []
    if (pendings.value.pendingReserves > 0) {
      alerts.push(`${pendings.value.pendingReserves} reserva(s) pendiente(s) de aprobación`)
    }
    if (pendings.value.pendingNotices > 0) {
      alerts.push(`${pendings.value.pendingNotices} noticia(s) pendiente(s) de moderación`)
    }
    if (pendings.value.pendingEvents > 0) {
      alerts.push(`${pendings.value.pendingEvents} evento(s) pendiente(s) de confirmación`)
    }
    try {
      const summary = await quotaStore.getAdminMonthlySummary({ month, year })
      if (summary?.code === 200) {
        const s = summary.data
        if (s?.total_pending > 0) {
          alerts.push(`S/. ${Number(s.total_pending).toFixed(2)} en cuotas pendientes de cobro este mes`)
        }
      }
    } catch (e) { /* ignore */ }

    try {
      const expRes = await expenseStore.getExpenses({ month, year, status: 1, per_page: 1 })
      if (expRes?.code === 200 && expRes.data?.total > 0) {
        alerts.push(`${expRes.data.total} gasto(s) pendiente(s) de aprobación este mes`)
      }
    } catch (e) { /* ignore */ }

    alertMessages.value = alerts
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDashboardData()
  setTimeout(() => {
    showTicker.value = false
  }, 30000)
})
</script>
<template>
  <div class="h-full w-full px-2 pb-4" style="overflow: auto;">
    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>
    <template v-else>
      <div v-if="alertMessages.length > 0" class="row md:px-28 q-mb-none q-mt-sm">
        <div class="col-12">
          <q-banner class="bg-orange-1 text-orange-9 rounded-borders q-mb-sm" v-for="(msg, i) in alertMessages" :key="i">
            <template v-slot:avatar>
              <q-icon name="eva-alert-triangle-outline" color="orange" />
            </template>
            {{ msg }}
          </q-banner>
        </div>
      </div>
      <div v-if="alertMessages.length === 0 && showTicker" class="row md:px-28 q-mb-none q-mt-sm">
        <div class="col-12">
          <div class="ticker-wrap bg-green-600 rounded-borders">
            <div class="ticker">
              <div class="ticker-item text-white">
                No hay alertas pendientes — todo está al día
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="row md:pt-2 pt-2 md:px-28">
        <div class="col-md-3 col-6 px-7 my-3" v-for="(item) in menu" :key="item.title">
          <div class="boxItem relative" @click="goTo(item.link)">
            <div v-if="badgeCount(item.badgeKey) > 0" class="badgeCountReserve flex flex-center">
              {{ badgeCount(item.badgeKey) }}
            </div>
            <div class="flex justify-center items-center h-full w-full p-1">
              <img :src="item.icon" class="md:w-auto h-3/5" />
            </div>
          </div>
          <div class="text-center mt-2 text-title-squad ellipsis">{{ item.title }}</div>
        </div>
      </div>
    </template>
  </div>
</template>
<style lang="scss">
.rounded-borders { border-radius: 0.5rem; }

.ticker-wrap {
  width: 100%;
  overflow: hidden;
  height: 25px;
  line-height: 25px;
  box-sizing: border-box;
}

.ticker {
  display: inline-block;
  white-space: nowrap;
  padding-left: 100%;
  animation: ticker-animation 15s linear infinite;
}

.ticker-item {
  display: inline-block;
  padding: 0 2rem;
  font-size: 1rem;
  font-weight: 500;
}

@keyframes ticker-animation {
  0% { transform: translate3d(0, 0, 0); }
  100% { transform: translate3d(-100%, 0, 0); }
}

.badgeCountReserve {
  height: 1.5rem;
  min-width: 1.5rem;
  border-radius: 0.75rem;
  background: red;
  position: absolute;
  color: white;
  font-size: 0.8rem;
  font-weight: 500;
  top: -8px;
  right: -8px;
  padding: 0 4px;
  z-index: 2;
}
</style>
