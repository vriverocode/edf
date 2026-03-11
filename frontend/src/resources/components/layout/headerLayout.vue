<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { inject, ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useNotificationsStore } from '@/services/store/notifications.store'
import iconsApp from '@/assets/icons/index'

import logo from '@/assets/img/logo/logo-white.webp'
const route = useRoute()
const router = useRouter()
const { user } = storeToRefs(useAuthStore())
const notificationsStore = useNotificationsStore()
const emitter = inject('emitter')
const materialIcons = inject('materialIcons')
const pagTitle = ref(route.meta.pagTitle)
const homePagesNameToHeader = ['dashboardAdmin', 'financePage', 'usersAdmin']
const isHomePage = ref(homePagesNameToHeader.includes(route.name))
const changePagTitle = (title) => {
  pagTitle.value = title
}
watch(route, (newRoute) => {
  pagTitle.value = newRoute.meta.pagTitle
  isHomePage.value = homePagesNameToHeader.includes(newRoute.name)
});




onMounted(() => {
  emitter.on('pagTitle', changePagTitle)
})

</script>

<template>
  <section class="md:px-8 md:mx-28 pb-3 px-6 flex justify-between items-stretch bg-primary header__container">
    <div class="flex flex-col justify-between">
      <div class="pt-1">
        <div v-html="iconsApp.menuDots" style="transform: translateX(-0.2rem);" />
      </div>
      <template v-if="isHomePage">
        <div class="text-white mt-3 mb-2">
          <div class="mant-title" style="font-weight:400; ">¡Hola!</div>
          <div class="text-nameHeader" style="">{{ user.name }}</div>
        </div>
        <div class="text-white">
          <div class="mant-title">Mantenimiento Febrero</div>
          <div class="text-amtHeader" style="">375.25</div>
        </div>
      </template>
      <template v-else>
        <div class=" text-pagtitle text-white">
          {{ pagTitle }}
        </div>
      </template>

    </div>
    <div class="flex items-start">
      <img :src="logo" alt="PACIFIK-LOGO-WHITE" class="imgLogoHeader"
        :class="{ 'mt-8 h-28': isHomePage, 'mt-5 h-20': !isHomePage }">
      <div class="relative" @click="router.push({ name: 'notificationsPage' })">
        <q-badge class="badgeNotificationCount pt-1" v-if="notificationsStore.unreadCount > 0" color="red"
          :label="notificationsStore.unreadCount" />
        <q-icon :name="materialIcons.roundNotifications" color="white" size="1.8rem" />
      </div>
    </div>
  </section>
</template>

<style lang="scss">
.imgLogoHeader {
  transition: all 0.5s ease;
}

.text-pagtitle {
  font-size: 1rem;
}

.text-nameHeader {
  font-weight: 500;
  text-transform: capitalize;
  font-size: 1.5rem;
}

.mant-title {
  font-size: 0.98rem;
  font-weight: 400;
}

.text-amtHeader {
  font-size: 2rem;
  font-weight: 400;
}

.badgeNotificationCount {
  top: -0.1rem;
  right: -0.3rem;
  position: absolute;
  z-index: 2;
}

.header__container {
  //box-shadow: 0px -0.1rem 1rem 0px rgb(0 0 0 / 38%);
  border-radius: 1rem;
  transition: all 0.5s ease;
}


@media (max-width: 780px) {
  .text-amtHeader {
    font-size: 1.8rem;
  }

  .mant-title {
    font-size: 0.8rem;
    font-weight: 400;
  }

  .text-nameHeader {

    font-size: 1rem;
  }
}
</style>