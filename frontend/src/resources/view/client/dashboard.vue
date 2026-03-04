<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router';
import anuncios from '@/assets/img/menu/anuncios.svg'
import atencion from '@/assets/img/menu/atencion.svg'
import mi_departamento from '@/assets/img/menu/mi_departamento.svg'
import mis_reservas from '@/assets/img/menu/mis_reservas.svg'
import pagos from '@/assets/img/menu/pagos.svg'
import eventos from '@/assets/img/menu/eventos.svg'
import { computed } from 'vue';

const { user } = storeToRefs(useAuthStore());
const router = useRouter();
const menu = [

  {
    title: 'Mi Departamento',
    icon: mi_departamento,
    link: '/client/department/options',
    roles: [2]
  },
  {
    title: 'Reservas',
    icon: mis_reservas,
    link: '/client/reserves/list',
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
    icon: atencion,
    link: '/client/services/list',
  },
  {
    title: 'Familiares',
    icon: atencion,
    link: '/client/familiar/list',
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
    <div class="row md:pt-10 pt-2  md:px-20" style="overflow-y: auto; height: 100%;">
      <div class="col-md-3 md:px-20 col-6 px-7 my-3" v-for="(items, key) in menuByRol" :key="key"
        >
        <div class="px-3">
          <div class="boxItem " @click="goTo(items.link)" >
            <div class="flex justify-center items-center h-full w-full p-1">
              <img :src="items.icon" class="w-full md:w-auto h-full" />
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