<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import dashboardAdmin from '@/view/admin/dashboard.vue';
import dashboardClient from '@/view/client/dashboard.vue';
import { ref } from 'vue';
const ready = ref(false)
const { user } = storeToRefs(useAuthStore())

setTimeout(() => {
  ready.value = true
}, 2000);
</script>
<template>
  <div>

    <dashboard-admin v-if="user.rol_id == 1" />
    <dashboard-client v-else />

    <!-- <dashboard-client v-if="user.rol_id == 2" /> -->
  </div>

</template>