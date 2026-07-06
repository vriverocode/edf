<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';
import metodo from '@/assets/img/menu/metodo-de-pago.png'
import financiera from '@/assets/img/menu/cuentas-financieras.png'



const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
    {
        title: 'Métodos de pagos',
        icon: metodo,
        link: '/admin/account-bank',
        roles: [1]
    },
    {
        title: 'Cuentas financieras',
        icon: financiera,
        link: '/admin/financial-accounts',
        roles: [1]
    },

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
            <div class="col-md-3 col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
                <div class="boxItem" @click="goTo(item.link)">
                     <div class="flex justify-center items-center h-full w-full p-1">
                        <img :src="item.icon" class="md:w-auto h-3/5" />
                    </div>
                </div>
                <div class="text-center mt-2  text-title-squad ">
                    {{ item.title }}
                </div>
            </div>

        </div>
    </div>
</template>

<style lang="scss" scoped></style>