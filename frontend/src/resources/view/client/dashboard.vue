<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router';
import { computed, watch } from 'vue'; // Agregamos watch aquí
import iconsApp from '@/assets/icons/index';

import anuncios from '@/assets/img/menu/anuncios.svg'
import atencion from '@/assets/img/menu/atencion.svg'
import mi_departamento from '@/assets/img/menu/mi_departamento.svg'
import mis_reservas from '@/assets/img/menu/mis_reservas.svg'
import defaulticon from '@/assets/img/menu/default-dash.svg'
import pagos from '@/assets/img/menu/pagos.svg'
import residentes from '@/assets/img/menu/residentes.png'
import visitas from '@/assets/img/menu/visitas.png'
import eventos from '@/assets/img/menu/eventos.svg'
import mantenimientos from '@/assets/img/menu/worker.png'
import booking from '@/assets/img/menu/reservas.png'


const { user } = storeToRefs(useAuthStore());
const router = useRouter();

const hasQuotasPending = computed(() => {
  let quotas = 0;
  if (user.value && user.value.units) {
    user.value.units.forEach(unit => {
      quotas += unit.pending_quotas_count;
    });
  }
  return quotas;
})

const menu = computed(() => [
  {
    title: 'Mi Unidad',
    icon: mi_departamento,
    link: '/client/department/options',
    badgePay: hasQuotasPending.value > 0,
    roles: [2,7]
  },
  {
    title: 'Reservas',
    icon: mis_reservas,
    link: '/client/reserves/list',
    roles: [2, 3, 4, 5]
  },
  {
    title: 'Pagos',
    icon: pagos,
    link: '/client/pays/menu',
    roles: [2,7]
  },
  {
    title: 'Eventos',
    icon: eventos,
    link: '/client/events',
    roles: [2, 3, 4, 5]
  },
  {
    title: 'Anuncios',
    icon: anuncios,
    link: '/client/notices/list',
    roles: [2]
  },
  {
    title: 'Atención',
    icon: atencion,
    link: '/client/incidents',
    roles: [2]
  },
  {
    title: 'Residentes',
    icon: residentes,
    link: '/client/familiar/list',
    roles: [2]
  },
  {
    title: 'Visitas',
    icon: visitas,
    link: '/client/visit/list',
    roles: [2]
  },
  {
    title: 'Reservas',
    icon: mis_reservas,
    link: '/security/reserves/list',
    roles: [6]
  },
  {
    title: 'Historial de reservas',
    icon: mis_reservas,
    link: '/reserves/transparency',
    roles: [6]
  },
  {
    title: 'Departamentos',
    icon: mi_departamento,
    link: '/security/departments/list',
    roles: [6]
  },
  {
    title: 'Airbnb',
    icon: iconsApp.visitorCard,
    link: '/security/airbnb/list',
    roles: [6]
  },
  {
    title: 'Visitas',
    icon: iconsApp.visitor,
    link: '/security/visit/list',
    roles: [6]
  },
  {
    title: 'Mantenimientos',
    icon: mantenimientos,
    link: '/admin/maintenances',
    roles: [6]
  },
  {
    title: 'Historial de reservas',
    link: '/client/reserves/history',
    icon: booking,
    roles: [2, 3, 4, 5]
  }
]);

const menuByRol = computed(() => {
  const rol = user.value?.rol_id;
  return menu.value.filter(item => !item.roles || item.roles.includes(rol));
})

const goTo = (url) => {
  router.push(url)
}
</script>

<template>
  <div class="h-full w-full px-2 ">
    <div class="row md:pt-10 pt-2  md:px-20 pb-12" style="overflow-y: auto; max-height: 100%;">
      <div class="col-md-3 col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="px-3">
          <div class="boxItem " @click="goTo(item.link)">
            <div v-if="item.badgePay">
              <div class="bagdePay" />
            </div>
            <div class="flex justify-center items-center h-full w-full p-1">
              <div v-html="item.icon" class="flex justify-center mt-0" v-if="user.rol_id == 6 && (item.title == 'Airbnb' || item.title == 'Visitas')" />
              <img :src="item.icon" class="w-auto md:w-auto " v-else
                :class="{ 'h-3/5': item.icon.includes('visitas') || item.icon.includes('residentes') || item.title.includes('Mantenimientos') || item.link.includes('history'), 
                'h-full': !item.icon.includes('visitas') && !item.icon.includes('residentes') && !item.title.includes('Mantenimientos') && !item.link.includes('history')}" />
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