<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { inject, ref, onMounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useNotificationsStore } from '@/services/store/notifications.store'
import iconsApp from '@/assets/icons/index'

import logo from '@/assets/img/logo/logo-white.webp'
const route = useRoute()
const router = useRouter()
const { user } = storeToRefs(useAuthStore())
const notificationsStore = useNotificationsStore()
const emitter = inject('emitter')
const pagTitle = ref(route.meta.pagTitle)
const homePagesNameToHeader = ['dashboardAdmin', 'financePage', 'usersAdmin', 'ProfileMenu']
const isHomePage = ref(homePagesNameToHeader.includes(route.name))
const ifOwner = ref(user.value.rol.name.toLowerCase() == 'propietario')
const reserveAreaActive = ref(false)
const mediaUrl = import.meta.env.VITE_LARAVEL_MEDIA_URL

const reserveInfo = ref({
  step: 1,
  icon: null,
  name: ''
})

const setReserveData = (e) => {
  reserveInfo.value = e.data
  reserveAreaActive.value = e.visible
}
const changePagTitle = (title) => {
  pagTitle.value = title
}
watch(route, (newRoute) => {
  pagTitle.value = newRoute.meta.pagTitle
  console.log('dddddd')
  isHomePage.value = homePagesNameToHeader.includes(newRoute.name)
});


const hasPendingToPay = computed(() => {
  let quotas = 0;
  user.value.units.forEach(unit => {
    quotas += unit.pending_quotas_count;
  });
  return quotas;
})

const goTo = (url) => {
  router.push(url)
}
onMounted(() => {
  emitter.on('pagTitle', changePagTitle)
  emitter.on('isReserve', setReserveData)

})

</script>

<template>
  <div class="md:px-8 md:mx-28 pb-2 px-6 row bg-primary header__container">
    <section class=" flex justify-between items-stretch col-12">
      <div class="flex flex-col  justify-between">
        <div class="pt-2 flex items-center" @click="goTo('/client/legals/menu')">
          <div v-html="iconsApp.menuDots" style="transform: translateX(-0.2rem);" />
          <div class="text-white" style="font-weight: 500; text-decoration: underline; cursor: pointer;">
            Legales
          </div>
        </div>
        <template v-if="isHomePage">
          <div class="text-white mt-3 mb-2">
            <div class="mant-title" style="font-weight:400; ">¡Hola!</div>
            <div class="text-nameHeader" style="">{{ user.name }}</div>
          </div>
          <div class="text-white" v-if="ifOwner && hasPendingToPay > 0">

            <div class="mant-title">Mantenimiento</div>
            <div class="text-amtHeader" style="">{{ user.currency_symbol }}

              {{ user.total_peding_quotas.toFixed(2) }}

            </div>
          </div>

        </template>
        <template v-if="!isHomePage && !reserveAreaActive">
          <div class=" text-pagtitle text-white">
            {{ pagTitle }}
          </div>
        </template>

      </div>
      <div class="flex items-start">
        <div class="flex items-end h-full">
          <img :src="logo" alt="PACIFIK-LOGO-WHITE" class="imgLogoHeader mb-2"
            :class="{ 'h-20 md:h-24': isHomePage, 'h-20': !isHomePage, 'toBottomFixed': reserveAreaActive }">
        </div>
        <div class="relative pt-2" @click="router.push({ name: 'notificationsPage' })">
          <q-badge class="badgeNotificationCount " v-if="notificationsStore.unreadCount > 0" color="red"
            :label="notificationsStore.unreadCount" />
          <q-icon name="eva-bell-outline" color="white" size="1.8rem" />
        </div>
      </div>
    </section>
    <div class="col-12" v-if="reserveAreaActive">
      <div style="transform: translateY(-1rem);">
        <div class="text-white text-reserveTitle pl-0" style="position:relative; z-index:2">
          Reservar
        </div>
        <div class="flex items-center pl-1">
          <img :src="mediaUrl + '/images/icons/' + reserveInfo.icon " alt=""
            style="height:4.5rem; transform:translateX(-15px) translateY(-5px)">
          <div class="text-reserveData" style="transform:translateX(-15px);">
            {{ reserveInfo.name }}
          </div>
        </div>
      </div>
      <div></div>
    </div>
  </div>
</template>

<style lang="scss">
.toBottomFixed {
  transform: translateY(80%);
}

.text-reserveData {
  font-size: 1.2rem;
  color: white;
  font-weight: medium;
}

.text-reserveTitle {
  font-size: 1rem;
  color: white;
  font-weight: medium;
}

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
  font-size: 1.9rem;
  font-weight: 500;
}

.badgeNotificationCount {
  top: 0.19rem;
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