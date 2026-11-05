<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { useConfigStore } from '@/services/store/config.store';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed, onMounted } from 'vue';
import { Notify } from 'quasar';

import budget from '@/assets/img/menu/presupuesto.png'
import baccount from '@/assets/img/menu/balance-cuentas.png'
import account from '@/assets/img/menu/cuentas.png'
import cuotas from '@/assets/img/menu/cuotas2.png'
import gastos from '@/assets/img/menu/gastos2.png'
import pays from '@/assets/img/menu/pagos.svg' 
import worker from '@/assets/img/menu/worker.png'
import cuotasEspeciales from '@/assets/img/menu/cuotas-especiales.png'
const { user } = storeToRefs(useAuthStore())
const configStore = useConfigStore()
const { balanceUrl } = storeToRefs(configStore)
const router = useRouter()
const menu = computed(() => [
  {
    title: 'Balance',
    icon: baccount,
    link: balanceUrl.value ? balanceUrl.value : 'https://edificiopacifik.com/descarga/balance-sep.pdf',
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
    title: 'Cargos Depto.',
    icon: cuotasEspeciales,
    link: '/admin/department-charges',
    roles: [1]
  },
  {
    title: 'Multas',
    icon: cuotasEspeciales,
    link: '/admin/multas',
    roles: [1]
  },
  {
    title: 'Proveedores',
    icon: worker,
    link: '/admin/providers/list',
    roles: [1]
  },
  {
    title: 'Pagos',
    icon: pays,
    link: '/admin/pays/menu',
    roles: [1]
  },
]);
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.value.filter(item => !item.roles || item.roles.includes(rol))
})
const goTo = (url) => {
  console.log(balanceUrl.value)
  console.log('url', url)
  if (!url) {
    Notify.create({
      color: 'negative',
      message: 'El balance no está disponible',
      timeout: 2000
    })
    return
  }

  if (url.startsWith('http://') || url.startsWith('https://')) {
    window.open(url, '_blank')
  } else {
    router.push(url)
  }
}
onMounted(async () => {
  try {
    await configStore.fetchBalanceUrl()
  } catch (error) {
    console.error('No se pudo cargar la URL del balance:', error)
  }
})
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(item.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <img :src="item.icon" class="md:w-auto" :class="{ 'h-3/5': !item.title.includes('Pagos'), 'h-full': item.title.includes('Pagos') }"
            />
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