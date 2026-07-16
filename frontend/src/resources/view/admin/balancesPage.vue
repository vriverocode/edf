<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';
import { Notify } from 'quasar'

import saldos from '@/assets/img/menu/saldos-pendientes.png'
import bills from '@/assets/img/menu/gastos-comunes.png'

const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Saldos pendientes',
    icon: saldos,
    link: '/admin/quotas/maintenance/list',
  },
  {
    title: 'Gastos comunes',
    icon: bills,
    link: '/admin/expenses/list',
  },

];
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.filter(item => !item.roles || item.roles.includes(rol))
})
const goTo = (item) => {
  if (item.link) router.push(item.link)
}
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="boxItem" :class="{ 'opacity-50': item.placeholder }" @click="goTo(item)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <div v-html="item.icon" class="flex justify-center mt-0" v-if="item.title == 'Cuotas' || item.title == 'Gastos'" />
            <img :src="item.icon" class="md:w-auto" v-else
            :class="{ 'h-3/5': item.icon.includes('default-dash'), 'h-3/5': !item.icon.includes('default-dash') }" />
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ item.title }}
        </div>
      </div>

    </div>
  </div>
</template>

<style lang="scss" scoped></style>