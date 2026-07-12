<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';

import budget from '@/assets/img/menu/presupuesto.png'
import baccount from '@/assets/img/menu/balance-cuentas.png'
import account from '@/assets/img/menu/cuentas.png'
import cuotas from '@/assets/img/menu/cuotas2.png'
import gastos from '@/assets/img/menu/gastos2.png'
import pays from '@/assets/img/menu/pagos.svg' 

const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Balance',
    icon: baccount,
    link: '/balances',
  },
  {
    title: 'Cuentas',
    icon: account,
    link: '/admin/accounts',
    roles: [1]
  },
  {
    title: 'Presupuesto',
    icon: budget,
    link: '/admin/monthly_bills/menu',
    roles: [1]
  },
  {
    title: 'Cuotas',
    icon: cuotas,
    link: '/admin/quotas/pays',
    roles: [1]
  },
  {
    title: 'Gastos',
    icon: gastos,
    link: '/admin/expenses/list',
    roles: [1]
  },
  {
    title: 'Pagos',
    icon: pays,
    link: '/admin/pays/menu',
    roles: [1]
  },

  

];
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  console.log(rol)
  return menu.filter(item => !item.roles || item.roles.includes(rol))
})
const goTo = (url) => {
  router.push(url)
}
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(item.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <img :src="item.icon" class="md:w-auto h-3/5" />
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