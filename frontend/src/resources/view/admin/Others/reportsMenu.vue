<script setup>
import { useRouter } from 'vue-router';
import booking from '@/assets/img/menu/reservas.png'
import gastos2 from '@/assets/img/menu/gastos2.png'
import cuotas from '@/assets/img/menu/cuotas2.png'
import morosos from '@/assets/img/menu/morosos.png'
import { onMounted, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@//services/store/auth.services';
const router = useRouter()
const { user } = storeToRefs(useAuthStore())
const menu = computed(() => [
  {
    title: 'Reporte de reservas',
    icon: booking,
    subtitle: 'Reservas con filtros y exportación',
    link: '/admin/reports/bookings',
    roles: [1,8],
  },
  {
    title: 'Reporte de gastos',
    icon: gastos2,
    subtitle: 'Gastos con filtros y totales por categoría',
    link: '/admin/reports/expenses',
    roles: [1,8],

  },
  {
    title: 'Reporte de morosos',
    icon: morosos,
    subtitle: 'Propietarios con cuotas pendientes',
    link: '/admin/reports/delinquents',
    roles: [1,8],

  },
  {
    title: 'Pagos mensuales',
    icon: cuotas,
    subtitle: 'Matriz de pagos por unidad y mes',
    link: '/admin/reports/monthly-pays',
    roles: [1,8],

  },
  {
    title: 'Cuotas por usuario',
    icon: cuotas,
    subtitle: 'Cuotas del mes agrupadas por usuario',
    link: '/admin/reports/user-quotas',
    roles: [1,8],

  },
  {
    title: 'Gastos por proveedor',
    icon: gastos2,
    subtitle: 'Matriz de gastos por proveedor y mes',
    link: '/admin/reports/expense-matrix',
    roles: [1,8],

  },
  {
    title: 'Reporte de pagos',
    icon: cuotas,
    subtitle: 'Pagos de cuotas con filtros y exportación',
    link: '/admin/reports/payments',
    roles: [1,8],

  },
   {
    title: 'Pagos mensuales',
    icon: cuotas,
    subtitle: 'Matriz de pagos por unidad y mes',
    link: '/admin/reports/monthly-pays',
    roles: [2, 3, 4],

  },
   {
    title: 'Reporte de reservas',
    icon: booking,
    subtitle: 'Reservas con filtros y exportación',
    link: '/admin/reports/bookings',
    roles: [2, 3, 4],
  },
]);

const menuByRol = computed(() => {
  const rol = user.value?.rol_id;
  return menu.value.filter(item => !item.roles || item.roles.includes(rol));
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
      <div class="col-md-3  col-6 px-7 my-3"  v-for="(item, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(item.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <div class="flex justify-center items-center h-full w-full p-1">
              <!-- <div v-html="item.icon" class="flex justify-center mt-0" v-if="user.rol_id == 6 && (item.title == 'Airbnb' || item.title == 'Visitas')" /> -->
              <img :src="item.icon" class="md:w-auto "
                :class="{ 'h-3/5': item.icon.includes('default-dash'), 'h-3/5': !item.icon.includes('default-dash') }" />
            
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis w-full">
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