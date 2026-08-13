<script setup>
import headerLayout from '@/components/layout/headerLayout.vue'
import infoNewSideBar from '@/components/layout/infoNewSideBar.vue'
import navbarAdmin from '@/components/layout/navbarAdmin.vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/services/store/auth.services'
import loaderPage from '@/components/layout/loaderPage.vue'
import { onMounted, ref, watch, inject, computed } from 'vue'
import budgetReminderBanner from '@/components/layout/budgetReminderBanner.vue'
import logoutModal from '@/components/layout/logoutModal.vue'
import firstTimeSetupModal from '@/components/layout/firstTimeSetupModal.vue'
import storage from '@/services/storage'
import { useNotificationsStore } from '@/services/store/notifications.store'
import { useQuasar } from 'quasar'
import { PushNotificationsService } from '@/services/notifications_push/pushNotifications'
import { Capacitor } from '@capacitor/core'
import backButton from '@/assets/img/menu/volver.png'

const isNative = ref(Capacitor.isNativePlatform())
const router = useRouter()
const route = useRoute()
const ready = ref(false)
const { user } = storeToRefs(useAuthStore())
const showModal = ref('')
const emitter = inject('emitter', null)
const notificationsStore = useNotificationsStore()
const $q = useQuasar()
const prevUnread = ref(0)
const lastShownId = ref(null)
const transitionName = ref('slide-up')
const showFirstTimeModal = computed(() => user.value?.is_first_time === 1)
const transitionName2 = ref('fade')
const reserveAreaActive = ref(false)
const homePagesNameToHeader = ['dashboardAdmin', 'financePage', 'usersAdmin', 'ProfileMenu']
const budgetBannerOffset = ref(0)

const hasPendingToPay = computed(() => {
  const units = user.value?.units ?? []
  return units.reduce((sum, unit) => sum + (unit.pending_quotas_count || 0), 0)
})

const headerSizeClass = computed(() => {
  if (reserveAreaActive.value && route.name === 'reserveClientAdd') return 'header--reserve'
  if (homePagesNameToHeader.includes(route.name)) {
    return hasPendingToPay.value > 0 ? 'header--home-quotas' : 'header--home'
  }
  return 'header--default'
})

watch(
  () => route.name,
  (name) => {
    if (name !== 'reserveClientAdd') reserveAreaActive.value = false
  }
)

const onBudgetBannerOffset = (px) => {
  budgetBannerOffset.value = px
}
const panelRootClass = computed(() => ({
  'pt-8': isNative.value && !budgetBannerOffset.value,
  'pt-2': !isNative.value && !budgetBannerOffset.value,
}))
const panelRootStyle = computed(() => {
  if (!budgetBannerOffset.value) {
    return {}
  }
  const base = isNative.value ? 32 : 8
  return { paddingTop: `${budgetBannerOffset.value + base}px` }
})
const goBack = () => {
  router.go(-1)
}
onMounted(() => {
  if (emitter) {
    emitter.on('logoutModal', () => {
      showModal.value = 'logout'
    })
    emitter.on('isReserve', (e) => {
      reserveAreaActive.value = e.visible
    })
  }
  useAuthStore()
    .currentUser()
    .then((response) => {
      if (user.value.rol_id) {
        ready.value = true
        getNotifications()
        PushNotificationsService.init()
      } else {
        throw response
      }
    })
    .catch(() => {
      console.error('ups')
      storage.deleteItem('access_token')
      router.push('/login')
    })
})

const getNotifications = () => {
  // Inicializar notificaciones
  notificationsStore.fetchUnreadCount().finally(() => {
    prevUnread.value = notificationsStore.unreadCount
  })
  notificationsStore.bindEchoListener(user.value.id)
}
const isShowablePage = () => {
  return ['reservePayConfirm'].includes(route.name)
}
const showNavbar = () => {
  return !['reserveConfirm', 'payConfirm'].includes(route.name)
}
const showBack = () => {
  return ![
    'dashboardAdmin',
    'financePage',
    'usersAdmin',
    'payConfirm',
    'reserveConfirm',
    'ProfileMenu',
  ].includes(route.name)
}

watch(
  () => notificationsStore.unreadCount,
  (newVal, oldVal) => {
    prevUnread.value = newVal
  }
)

watch(
  () => notificationsStore.lastIncoming,
  (notif) => {
    if (!notif) return
    // Evitar duplicados
    const id = notif.id || `${notif.title}-${notif.message}-${notif.url}-${Date.now()}`
    if (lastShownId.value === id) return
    lastShownId.value = id

    // Si es cliente y es una notificación de "Reserva creada", NO mostrar toast
    const isClient = user.value?.rol_id && user.value.rol_id != 1
    const title = notif.title || notif?.data?.title
    const color = notif?.meta?.color || notif?.data?.meta.color
    const message = notif.message || notif?.data?.message || 'Nueva notificación recibida'

    if (isClient && title === 'Reserva creada') {
      return
    }

    useAuthStore()
      .currentUser()
      .then((response) => {
        if (user.value.rol_id) {
          ready.value = true
          getNotifications()
          PushNotificationsService.init()
        } else {
          throw response
        }
      })
      .catch(() => {
        console.error('ups')
        storage.deleteItem('access_token')
        router.push('/login')
      })

    $q.notify({
      classes: 'q-mt-lg',
      color: color ?? 'primary',
      message: `${title ? title + '' : ''}`,
      icon: 'eva-bell-outline',
      position: 'top-right',
    })
  }
)

watch(
  () => route.meta.depth,
  (toDepth, fromDepth) => {
    transitionName.value = toDepth > fromDepth ? 'slide-up' : 'slide-down'
  }
)

watch(
  () => route.meta.depth,
  (toDepth, fromDepth) => {
    transitionName2.value = toDepth > fromDepth ? 'fade' : ''
  }
)
</script>

<template>
  <div class="" style="height: 100vh; width: 100%; overflow: hidden">
    <div
      class="panel-layout-root layout--default h-full bg-white w-full min-h-screen"
      :class="panelRootClass"
      :style="panelRootStyle"
    >
      <template v-if="ready">
        <budgetReminderBanner @offset="onBudgetBannerOffset" />
        <transition :name="'fade'">
          <headerLayout
            class="header__container w-100"
            v-if="!isShowablePage()"
            :class="headerSizeClass"
          />
        </transition>
        <section class="principal">
          <transition :name="transitionName2">
            <div
              class="row w-full backButton items-center md:px-20 md:mx-16 px-2"
              v-if="showBack()"
            >
              <div class="flex items-center" @click="goBack()">
                <q-btn
                  color="white"
                  flat
                  round
                  outline
                  class="text-backButton flex flex-center"
                  size="0.7rem"
                >
                  <img :src="backButton" alt="" style="height: 55px; width: 55px" />
                </q-btn>
                <div class="ml-0 pt-1 backButton-text">Volver</div>
              </div>
            </div>
          </transition>
          <div class="relative w-full overflow-hidden pt-3 page_continerContent">
            <router-view v-slot="{ Component, route }">
              <transition :name="transitionName">
                <component
                  :is="Component"
                  :key="route.fullPath"
                  class="inner-page-component pb-1"
                />
              </transition>
            </router-view>
          </div>
        </section>
        <navbarAdmin v-if="showNavbar()" @logoutModal="showModal = 'logout'" />
        <infoNewSideBar />
        <logoutModal :dialog="showModal == 'logout'" @closeModal="showModal = ''" />
        <firstTimeSetupModal :dialog="showFirstTimeModal" />
      </template>
      <loaderPage v-else />
    </div>
  </div>
</template>

<style lang="scss">
/* Safe areas: barra de estado (arriba) y barra de navegación/gestos (abajo) */
.panel-layout-root {
  padding-bottom: var(--safe-area-inset-bottom, env(safe-area-inset-bottom, 0px));
}

/* ── Esquema híbrido: header y navbar con altura fija en px, content con flex ── */
.layout--default {
  display: flex;
  flex-direction: column;

  .header__container {
    flex-shrink: 0;
    height: 112px;
    min-height: 0;
    max-height: none;

    &.header--home {
      height: 140px;
    }

    &.header--home-quotas {
      height: 168px;
    }

    &.header--reserve {
      height: 168px;
    }
  }

  @media (min-width: 1280px) {
    .header__container {
      height: 132px;

      &.header--home {
        height: 170px;
      }

      &.header--home-quotas {
        height: 196px;
      }

      &.header--reserve {
        height: 196px;
      }
    }
  }

  .principal {
    flex: 1;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }

  .page_continerContent {
    flex: 1;
    min-height: 0;
  }

  .inner-page-component {
    height: 100%;

    /* CRUCIAL PARA EL SCROLL */
    overflow-y: auto;
    overflow-x: hidden;

    /* Hardware acceleration para que la animación fluya mientras haces scroll */
    backface-visibility: hidden;
  }

  .backButton {
    height: 64px;
    flex-shrink: 0;

    & .q-btn--outline:before {
      border-width: 3px;
    }

    & .q-btn .q-icon {
      font-size: 2.1em;
    }
  }

  .text-backButton {
    color: #c9a344 !important;
  }

  .bg-backButton {
    background-color: #c9a344 !important;
  }

  .backButton-text {
    font-size: 1.1rem;
    color: rgb(63, 63, 63);
    font-weight: 500;
  }
}
</style>
