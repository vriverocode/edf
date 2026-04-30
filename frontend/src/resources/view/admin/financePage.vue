<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';

const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Balance de cuentas',
    icon: iconsApp.payHouse,
    link: '/balances',
  },
  {
    title: 'Cuentas',
    icon: iconsApp.bank,
    link: '/admin/accounts',
    roles: [1]
  },
  {
    title: 'Gastos Mensuales',
    icon: iconsApp.bills,
    link: '/admin/monthly_bills/menu',
    roles: [1]
  },
  {
    title: 'Pagos de cuotas',
    icon: iconsApp.cuotas,
    link: '/admin/quotas/pays',
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
    <div class="row md:pt-10 pt-2  md:px-20">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(items, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(items.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <!-- <img :src="items.icon" class="w-full md:w-auto h-full" /> -->
            <div v-html="items.icon" class="flex justify-center mt-0" />
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ items.title }}
        </div>
      </div>

    </div>
  </div>
</template>

<style lang="scss" scoped></style>