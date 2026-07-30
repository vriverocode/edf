<script setup>
import { useRouter } from 'vue-router';
import booking from '@/assets/img/menu/reservas.png'
import gastos2 from '@/assets/img/menu/gastos2.png'
import cuotas from '@/assets/img/menu/cuotas2.png'
import { onMounted } from 'vue';
import iconsApp from '@/assets/icons/index'

const router = useRouter()
const menu = [
    {
        title: 'Datos Personales',
        icon: iconsApp.profile,
        link: '/client/profile/edit',
    },
    {
        title: 'Datos bancarios',
        icon: iconsApp.wallet,
        link: '/client/account-bank',
    },

];
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.filter(item => !item.roles || item.roles.includes(rol))
})
const goTo = (url) => {
  router.push(url)
}

onMounted(() => {
  // getPendingCount()
})
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(item.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <div class="flex justify-center items-center h-full w-full p-1">
              <div v-html="item.icon" class="flex justify-center mt-0"  />
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ item.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.badgeCountReserve {
  height: 1.5rem;
  width: 1.5rem;
  border-radius: 0.4rem;
  background: red;
  position: absolute;
  color: white;
  font-size: 0.9rem;
  font-weight: 500;
  top: -10px;
  right: -5px;

}
</style>