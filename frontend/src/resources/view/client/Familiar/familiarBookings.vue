<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import { useQuasar } from 'quasar';
import iconsApp from '@/assets/icons/index';
import moment from 'moment';

const $q = useQuasar();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

moment.locale('es', {
    monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
    months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
});

const reserves = ref([]);
const loading = ref(false);
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL;

const getReserves = () => {
    loading.value = true;
    userStore.getResidentBookings(route.params.id)
        .then((response) => {
            if (response.code !== 200) throw response;
            reserves.value = response.data;
        })
        .catch((error) => {
            $q.notify({
                color: 'negative',
                message: error || 'Error al cargar reservas',
                timeout: 2000
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const getPaymentAmount = (booking) => {
    if (booking.amount > 0) {
        return `S/. ${booking.amount}`;
    }
    return 'Gratis';
};

onMounted(() => {
    getReserves();
});
</script>

<template>
    <div class="h-full" style="overflow: hidden;">
        <!-- Header -->
        <div class="px-4 md:px-20 pt-5 pb-2 flex items-center justify-between">
            <div class="text-h5 text-bold text-black flex items-center">
                <q-btn flat round icon="eva-arrow-ios-back-outline" color="grey-8" class="q-mr-sm" @click="router.go(-1)" />
                Reservas del residente
            </div>
        </div>

        <div style="height: 85%; overflow: auto;">
            <!-- Loading State -->
            <div v-if="loading" class="flex justify-center items-center py-20">
                <q-spinner-dots color="primary" size="7rem" />
            </div>

            <!-- Content -->
            <div v-else class="px-4 py-4 md:px-28">
                <!-- Lista de reservas -->
                <div v-if="reserves.length > 0" class="space-y-3 md:px-5">
                    <div v-for="reserve in reserves" :key="reserve.id"
                        class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
                        style="position: relative;">

                        <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
                            <!-- Header con nombre y estado -->
                            <div class="flex justify-between items-start mb-2 mt-2">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-gray-900 mb-2">
                                        {{ reserve.comun_area?.name || 'Área Común' }}
                                    </h3>
                                </div>
                                <!-- Estado badge -->
                                <span :class="'bg-' + reserve.status_color"
                                    class="inline-block px-3 py-1 mt-1 text-xs font-bold text-white badgeReserve" style="border-radius: 6px;">
                                    {{ reserve.status_label }}
                                </span>
                            </div>

                            <!-- Contenido principal con imagen y detalles -->
                            <div class="flex items-center space-x-4">
                                <!-- Imagen del área -->
                                <div class="w-16 h-16 rounded-xl flex items-center justify-center flex-shrink-0" style="background-color: #f1f5f9; padding: 10px;">
                                    <div class="flex justify-center items-center h-full w-full">
                                        <img :src="urlMedia + '/images/icons/' + (reserve.comun_area?.icon || 'default') " alt=""
                                            style="height:100%">
                                    </div>
                                </div>

                                <!-- Detalles de la reserva -->
                                <div class="flex-1 space-y-2 mt-2">
                                    <!-- Fechas -->
                                    <div class="flex items-center text-sm text-gray-700">
                                        <q-icon name="eva-calendar-outline" size="1.2rem" class="q-mr-sm text-grey-6" />
                                        <span class="font-medium">{{ moment(reserve.date).format('DD MMM YYYY') }}</span>
                                    </div>

                                    <!-- Horario -->
                                    <div class="flex items-center text-sm text-gray-700">
                                        <q-icon name="eva-clock-outline" size="1.2rem" class="q-mr-sm text-grey-6" />
                                        <span class="font-medium">
                                            {{ reserve.time_from }} - {{ reserve.time_to }}
                                        </span>
                                    </div>
                                    
                                    <!-- Monto y Tipo -->
                                    <div class="flex items-center text-sm text-gray-700">
                                        <div v-html="iconsApp.moneyIcon" class="q-mr-sm text-grey-6" style="width: 1.2rem; display: flex; justify-content: center;" />
                                        <span class="font-medium">
                                            {{ getPaymentAmount(reserve) }}
                                            <q-chip color="primary" v-if="reserve.type == 2" dense class="q-ml-sm">
                                                <div class="text-white" style="font-weight:600; font-size:0.7rem">
                                                    {{ reserve.type_label }}
                                                </div>
                                            </q-chip>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-center column empty-results mt-10">
                    <q-icon name="eva-calendar-outline" size="4rem" color="grey-5" class="q-mb-md" />
                    <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
                        No hay reservas registradas
                    </div>
                    <div class="text-grey-6 text-center">
                        Este usuario no ha realizado ninguna reserva de áreas comunes.
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.badgeReserve {
    border-radius: 8px;
}
</style>
