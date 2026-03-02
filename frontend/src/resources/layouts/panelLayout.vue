<script setup>
import headerLayout from '@/components/layout/headerLayout.vue';
import infoNewSideBar from '@/components/layout/infoNewSideBar.vue';
import navbarAdmin from '@/components/layout/navbarAdmin.vue';
import { useRoute, useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import loaderPage from '@/components/layout/loaderPage.vue';
import { onMounted, ref, watch, inject } from 'vue';
import logoutModal from '@/components/layout/logoutModal.vue';
import storage from '@/services/storage'
import { useNotificationsStore } from '@/services/store/notifications.store'
import { useQuasar } from 'quasar'
import { PushNotificationsService } from '@/services/notifications_push/PushNotifications';

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
const goBack = () => {
  router.go(-1)
}
onMounted(() => {
  if (emitter) emitter.on('logoutModal', () => { showModal.value = 'logout' })
  useAuthStore().currentUser()
    .then((response) => {
      if (user.value.rol_id) {
        ready.value = true
        getNotifications()
        PushNotificationsService.init();
      }
    })
    .catch(() => {
      console.log('ups')
      storage.deleteItem("access_token");
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
watch(() => notificationsStore.unreadCount, (newVal, oldVal) => {
  prevUnread.value = newVal
})

watch(() => notificationsStore.lastIncoming, (notif) => {
  if (!notif) return
  // Evitar duplicados
  const id = notif.id || `${notif.title}-${notif.message}-${notif.url}-${Date.now()}`
  if (lastShownId.value === id) return
  lastShownId.value = id

  // Si es cliente y es una notificación de "Reserva creada", NO mostrar toast
  const isClient = user.value?.rol_id && user.value.rol_id != 1
  const title = notif.title || notif?.data?.title
  const message = notif.message || notif?.data?.message || 'Nueva notificación recibida'

  if (isClient && title === 'Reserva creada') {
    return
  }

  $q.notify({
    classes: 'q-mt-lg',
    color: 'primary',
    message: `${title ? title + '' : ''}`,
    icon: 'eva-bell-outline',
    position: 'top-right'
  })
})
const isShowablePage = () => {
  return (['reserveConfirm', 'reservePay', 'reservePayConfirm', 'quotaPay'].includes(route.name))
}
const showNavbar = () => {
  return ['dashboardAdmin', 'financePage', 'usersAdmin'].includes(route.name)
}
const showBack = () => {
  return !(['dashboardAdmin', 'financePage', 'usersAdmin', 'reservePay', 'quotaPay'].includes(route.name))
}
</script>

<template>
  <div class="h-full bg-white w-full pt-8" style="position: relative; overflow: hidden;">
    <template v-if="ready">
      <headerLayout class="header__container" v-if="!isShowablePage()" />
      <section :class="{
        'withoutNav': isShowablePage(),
        'page__container': showNavbar(),
        'page_continerFull': !showNavbar()
      }">
        <transition name="horizontal">
          <div class="md:px-28 md:mx-28">
            <div class="backButton  flex items-center pt-4 px-4 w-max" v-if="showBack()" @click="goBack()">
              <q-btn outline round color="backButton" icon="eva-arrow-back-outline" />
              <div class="ml-2 backButton-text">REGRESAR</div>
            </div>
          </div>
        </transition>
        <router-view v-slot="{ Component }">
          <transition name="horizontal">
            <component :is="Component" style="height: 90%;" />
          </transition>
        </router-view>
      </section>
      <navbarAdmin v-if="['dashboardAdmin', 'financePage', 'usersAdmin'].includes(route.name)"
        @logoutModal="showModal = 'logout'" />
      <infoNewSideBar />

      <logoutModal :dialog="(showModal == 'logout')" @closeModal="showModal = ''" />
    </template>
    <loaderPage v-else />
  </div>
</template>

<style lang="scss">
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

.backButton {
  height: 10%;

  & .q-btn--outline:before {
    border-width: 3px;
  }

  & .q-btn .q-icon {
    font-size: 2.1em;
  }
}

.header__container {
  max-height: 23%;
  height: auto;
  min-height: 16%;
  overflow: hidden;
}

.page__container {
  height: 67%;
  overflow: hidden;
  // overflow-x: hidden;
  // overflow-y: auto;
}

.page_continerFull {
  height: 84%;
  overflow: hidden;
  // overflow-x: hidden;
  // overflow-y: auto;
}

.withoutNav {
  height: 100%;
  overflow: hidden;
}
</style>
