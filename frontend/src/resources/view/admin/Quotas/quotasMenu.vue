<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';
import cuotas from '@/assets/img/menu/cuotas-mantenimiento.png'
import especial from '@/assets/img/menu/cuotas-especiales.png'

const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Cuotas de mantenimiento',
    link: '/admin/quotas/maintenance/list',
    icon: cuotas,
    roles: [1],
  },
  // {
  //   title: 'Cuotas especiales',
  //   icon: especial,
  //   link: '/admin/accounts',
  //   roles: [1],
  // },

];
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.filter(item => !item.roles || item.roles.includes(rol))
})
const goTo = (url) => {
  router.push(url)
}
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(items, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(items.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <img :src="items.icon" class="w-full md:w-auto h-3/5" />
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad  ">
          {{ items.title }}
        </div>
      </div>

    </div>
  </div>
</template>

<style lang="scss" scoped></style>