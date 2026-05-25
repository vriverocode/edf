<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router';
import { computed, watch } from 'vue'; // Agregamos watch aquí

import anuncios from '@/assets/img/menu/anuncios.svg'
import atencion from '@/assets/img/menu/atencion.svg'
import mi_departamento from '@/assets/img/menu/mi_departamento.svg'
import mis_reservas from '@/assets/img/menu/mis_reservas.svg'
import defaulticon from '@/assets/img/menu/default-dash.svg'
import pagos from '@/assets/img/menu/pagos.svg'
import eventos from '@/assets/img/menu/eventos.svg'

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

// // Opcional: El watcher explícito si necesitas ejecutar lógica secundaria
// watch(user, (newVal) => {
//   console.log('Datos del usuario actualizados', newVal);
// }, { deep: true });

// // Convertimos el menú en un 'computed' para que badgePay reaccione a los cambios
const menu = computed(() => [
  {
    title: 'Mi Departamento',
    icon: mi_departamento,
    link: '/client/department/options',
    badgePay: hasQuotasPending.value > 0,
    roles: [2]
  },
  {
    title: 'Reservas',
    icon: mis_reservas,
    link: '/client/reserves/list',
    roles: [2, 3, 4]
  },
  {
    title: 'Pagos',
    icon: pagos,
    link: '/client/pays/list',
    roles: [2]
  },
  {
    title: 'Eventos',
    icon: eventos,
    link: '/client/events',
    roles: [2, 3, 4]
  },
  {
    title: 'Anuncios',
    icon: anuncios,
    link: '/client/notices/list',
  },
  {
    title: 'Atención',
    icon: atencion,
    link: '/client/services/list',
  },
  {
    title: 'Visitas',
    icon: defaulticon,
    link: '/client/visit/list',
    roles: [2]
  },
  {
    title: 'Residentes',
    icon: defaulticon,
    link: '/client/familiar/list',
    roles: [2]
  },
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
      <div class="col-md-3 col-6 px-7 my-3" v-for="(items, key) in menuByRol" :key="key">
        <div class="px-3">
          <div class="boxItem " @click="goTo(items.link)">
            <div v-if="items.badgePay">
              <div class="bagdePay" />
            </div>
            <div class="flex justify-center items-center h-full w-full p-1">
              <img :src="items.icon" class="w-full md:w-auto "
                :class="{ 'h-3/5': items.icon.includes('default-dash'), 'h-full': !items.icon.includes('default-dash') }" />
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad">
          {{ items.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped></style>