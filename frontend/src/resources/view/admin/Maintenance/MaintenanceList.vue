<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMaintenanceStore } from '@/services/store/maintenance.store';
import moment from 'moment';

const router = useRouter();
const maintenanceStore = useMaintenanceStore();

const maintenances = ref([]);
const loading = ref(true);

const getMaintenances = () => {
  loading.value = true;
  maintenanceStore.getMaintenances()
    .then((response) => {
      maintenances.value = response.data || response;
    })
    .catch((error) => {
      console.error(error);
    })
    .finally(() => {
      loading.value = false;
    });
};

const getStatusColor = (status) => {
  switch (Number(status)) {
    case 0: return 'negative';    // Cancelado
    case 1: return 'blue-7';      // Pendiente
    case 2: return 'positive';    // Completado
    case 3: return 'warning';     // Pendiente de material
    default: return 'grey-7';
  }
};

const goToCreate = () => {
  router.push('/admin/comun-area/list');
};

const goToDetail = (id) => {
  router.push(`/admin/maintenances/${id}`);
};

const goToComplete = (id) => {
  router.push(`/admin/maintenances/${id}/complete`);
};

const goToChangeStatus = (id) => {
  router.push(`/admin/maintenances/${id}/status`);
};

onMounted(() => {
  getMaintenances();
});
</script>

<template>
  <div class="h-full flex flex-column justify-between" style="overflow: hidden;">
    <div style="height: 90%; overflow: auto;" class="w-full">
      
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 py-6 md:px-28">
        <div v-if="maintenances.length > 0" class="space-y-3 md:px-5">
          <div 
            v-for="item in maintenances" 
            :key="item.id"
            class="p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow cursor-pointer q-mb-md"
            @click="goToDetail(item.id)"
          >
            <div class="row items-center justify-between">
              <div class="col-8">
                <div class="text-subtitle1 text-bold text-gray-900">{{ item.title }}</div>
                <div class="text-caption text-grey-7 mt-1 row items-center">
                  <q-icon name="eva-calendar-outline" size="1rem" class="q-mr-xs" />
                  {{ moment(item.date).format('DD/MM/YYYY') }} 
                  <span class="q-mx-sm">|</span>
                  <q-icon name="eva-clock-outline" size="1rem" class="q-mr-xs" />
                  <template v-if="item.time_from && item.time_to">{{ item.time_from.substring(0,5) }} - {{ item.time_to.substring(0,5) }}</template>
                  <template v-else>Todo el día</template>
                </div>
                <div v-if="item.comun_area" class="mt-2">
                  <q-chip dense color="teal-1" text-color="teal-9" icon="eva-building-outline" size="sm" class="text-bold">
                    Área: {{ item.comun_area.name }} (Bloqueada)
                  </q-chip>
                </div>
              </div>

              <div class="col-4 flex justify-end">
                <q-chip 
                  :color="getStatusColor(item.status)" 
                  text-color="white" 
                  class="text-bold px-3"
                  dense
                >
                  {{ item.status_label }}
                </q-chip>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
          <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4 mx-auto">
            <q-icon name="eva-settings-2-outline" size="2.5rem" color="grey-5" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-1">No hay mantenimientos programados</h3>
          <p class="text-gray-500 max-w-sm">Todas las instalaciones y áreas comunes se encuentran operando al 100%.</p>
        </div>
      </div>
    </div>

    <div class="px-4 md:flex md:justify-center items-center w-full md:px-12 pb-4" style="min-height: 10%;">
      <q-btn 
        color="primary" 
        unelevated 
        class="w-full mt-0 md:mx-24 font-bold"
        style="border-radius: 0.5rem;" 
        @click="goToCreate"
        no-caps
      >
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" class="q-mr-xs" />
          <span>Programar Mantenimiento</span>
        </div>
      </q-btn>
    </div>
  </div>
</template>