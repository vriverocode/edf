<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { Capacitor } from '@capacitor/core';
import { ref } from 'vue'

const isNative = ref(Capacitor.isNativePlatform());
const emit = defineEmits(['logoutModal'])
const { user } = storeToRefs(useAuthStore())
const logout = () => {
  emit('logoutModal')
}
const availableByRol = (roles) => {
  // user.rol
  if(roles.lenght >= 0) {
    return true
  }
  return roles.includes(user.value.rol.name.toLowerCase())
};

</script>
<template>
  <div class="bottom-tab " :class="{ 'spaceBarBottom': isNative, 'total-h': !isNative }">
    <q-tabs no-caps right-icon="-" active-color="terciary" align="justify"
      class="bg-white text-dark shadow-0  q-py-md-xs q-px-md-lg flex q-py-xs userNavbar">
      <q-route-tab class="q-px-xs-sm q-pt-sm q-px-md-lg" :to="'/dashboard'" exact>
        <div class="flex flex-center column">
          <div v-html="iconsApp.home3" />
          <span class="q-mt-xs text-dark text-subtitle2">Inicio</span>
        </div>
      </q-route-tab>
      <q-route-tab class="q-px-xs-sm q-pt-sm q-px-md-lg" v-if="availableByRol(['admin'])" :to="'/admin/users'" exact>
        <div class="flex flex-center column">
          <div v-html="iconsApp.user3" />
          <span class="q-mt-xs text-dark text-subtitle2">Usuarios</span>
        </div>
      </q-route-tab>
      <q-route-tab class="q-px-xs-sm q-pt-sm q-px-md-lg" :to="'/admin/finance'" v-if="availableByRol(['admin', 'propietario'])" exact>
        <div class="flex flex-center column">
          <div v-html="iconsApp.finance2" />
          <span class="q-mt-xs text-dark text-subtitle2">Finanzas</span>
        </div>
      </q-route-tab>
      <q-route-tab class="q-px-xs-sm q-pt-sm q-px-md-lg" @click="logout()" >
        <div class="flex flex-center column">
          <div v-html="iconsApp.exit2" />
          <!-- <q-icon name="eva-log-out-outline" size="31px" color="grey-6" /> -->
          <span class="q-mt-xs text-dark text-subtitle2 ">Salir</span>
        </div>
      </q-route-tab>


    </q-tabs>
  </div>
</template>


<style lang="scss">
.userNavbar {

  padding-top: 0px !important;

  & .q-tab__indicator {
    bottom: 97% !important;
    display: none;
  }

  & .q-tab--active {
    & span {
      color: #02205d !important;
    }

    & path {
      stroke: #02205d !important;
    }
  }
}

.q-tab__label {
  font-size: 0.72rem !important;
}
</style>
<style lang="scss" scoped>
.notificationBadge1 {
  height: 15px;
  width: 15px;
  background: red;
  position: absolute;
  border-radius: 50%;
  left: 65%;
  bottom: 55%;
}

.w-100 {
  width: 100% !important;
}

.bottom-tab {
  /* Safe area: evita solapamiento con barra de navegación (3 botones o gestos) */
  border-top: 1.5px solid $grey-5;
  width: 100%;
  
  z-index: 2;
}
.spaceBarBottom {

  height: 10%;
}

.total-h {
  position: fixed;
  bottom: 0;
}

@media screen and (max-width: 820px) {
  .bottom-tab {
    width: 100%;
    left: 0%;
  }
}
</style>