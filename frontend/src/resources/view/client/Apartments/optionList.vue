<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import bg from '@/assets/img/util/bg3.webp'
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';
import pagos from '@/assets/img/menu/mis-pagos.png'
import departamentos from '@/assets/img/menu/departamentos.png'
import cuotas from '@/assets/img/menu/cuotas-mantenimiento.png'


const { user } = storeToRefs(useAuthStore())
const hasQuotasPending = computed(() => {
  let quotas = 0;
  user.value.units.forEach(unit => {
    quotas += unit.pending_quotas_count;
  });
  return quotas;
})
const router = useRouter()
const menu = [
  {
    title: 'Mis pagos',
    icon: pagos,
    link: '/client/pays/list',
  },
  {
    title: 'Mis propiedades',
    icon: departamentos,
    link: '/client/department/my-unit',
  },
  {
    title: 'Cuotas / Mantenimiento ',
    icon: cuotas,
    link: '/client/balance/list',
    badgePay: hasQuotasPending.value > 0,
  },


];

const goTo = (url) => {
  router.push(url)
}
</script>
<template>

  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-5  md:px-20">
      <div class="col-md-3  col-6 px-7 my-3" v-for="(item, key) in menu" :key="key">
        <div class="px-3">
          <div class="boxItem " @click="goTo(item.link)">
            <div v-if="item.badgePay">
              <div class="bagdePay" />
            </div>
            <div class="flex justify-center items-center h-full w-full p-1">
              <img :src="item.icon" class="h-3/5" />
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad">
          {{ item.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped></style>