<script setup>
import { ref, onMounted } from 'vue';
import { usePayMethodStore } from '@/services/store/payMethod.store';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import disabledModal from '@/components/payMethods/disabledModal.vue';

const payMethods = ref([]);
const loading = ref(false);
const payMethodStore = usePayMethodStore();
const router = useRouter();
const dialog = ref('');
const selectedPayMethod = ref({})
// const filter = ref({
//   status: 4,
//   area_id: '',
//   date_from: '',
//   date_to: '',
//   amount_type: '',
//   sort_by: 'created_at',
//   sort_dir: 'desc'
// })
const getPayMethod = () =>{
  loading.value = true;
  payMethodStore.getPayMethod()
    .then((response) => {
      if (response.code !== 200) throw response;
      payMethods.value = response.data;

    })
    .catch((response) => {
      console.log(response);
    })
    .finally(() => {
      loading.value = false;
    });
}

const goTo = (url) => {
  router.push(url);
}
const showDialog = (e) => {
    const dialogData = getDialogData(e)
    selectPaymethod(dialogData.method)
    setTimeout(() => {
        dialog.value = dialogData.dialog;
    }, 400);
}

const selectPaymethod = (id) => {
  selectedPayMethod.value = payMethods.value.find(payMethod => payMethod.id == id)
  console.log(selectedPayMethod.value)
}
const getDialogData = (e) => {
  return e.target.closest('.q-item').dataset
}
const hiddenModal = () => {
  dialog.value = ''
}
onMounted(() => {
    getPayMethod();
});
</script>
<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 pb-6 pt-3 md:px-28">
        <div class="flex justify-end md:pr-5 pr-1">
          <q-btn
            outline
            color="primary"
            icon="eva-plus-outline"
            label="Agregar"
            @click="goTo('/admin/account-data/add')"
          />
        </div>
        <!-- Lista de reservas -->
        <div v-if="payMethods.length > 0" class="space-y-3 pt-3 md:px-5">
          <div v-for="payMethod in payMethods" :key="payMethod.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5" style="position: relative;">

            <!-- Sección superior - Detalles de la reserva -->
            <div class="px-0 pb-4 pt-2 border-b border-dashed border-gray-300">
              <!-- Header con nombre y estado -->
              <div class="flex justify-between items-start mb-2 px-4">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-0">
                    {{ payMethod.name }}
                  </h3>
                </div>
                <!-- Estado badge -->
                <span :class="payMethod.status_color"
                  class="inline-block px-3 py-2 text-xs font-bold text-white badgePayMethod">
                  {{ payMethod.status_label }}
                </span>
              </div>

              <!-- Contenido principal con imagen y detalles -->
              <div class="flex row items-end ">
                <!-- Imagen del área -->

                <!-- Detalles de la reserva -->
                <div class="flex-1  col-12 pl-4">
                  <!-- Area -->
                  <div class="flex items-center text-sm text-gray-700" style="margin-top: 3px;" 
                    v-for="(data, key) in payMethod.data" :key="key">
                    <div>{{data.title}}:</div>
                    <div class="ml-1 font-medium">{{data.data}}</div>
                  </div>

                </div>
              </div>
            </div>

            <!-- Sección inferior - Estado de pago -->
            <div class="py-2 px-4 bg-gray-50">
              <div class="flex justify-end items-center">
                <div class="flex items-center">
                  <div flat rounded color="primary" size="sm" class="ml-3 cursor-pointer" >
                    <div v-html="iconsApp.optionsBook"></div>
                    <q-menu>
                    <q-list style="min-width: 150px">
                      <q-item clickable v-close-popup @click="goTo('/admin/account-data/update/'+payMethod.id)">
                        <q-item-section>Editar</q-item-section>
                      </q-item>
                      <q-item clickable v-close-popup  data-dialog="deshabilitar" 
                        :data-method="payMethod.id" @click="showDialog($event)">
                        <q-item-section>
                            {{ payMethod.status == 0 ? 'Habilitar' : 'Deshabilitar'}}
                        </q-item-section>
                      </q-item>
                      <q-separator />
                    </q-list>
                  </q-menu>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
              </path>
            </svg>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay metodos de pago agregados</h3>
          <p class="text-gray-600 text-center mb-6">Puedes agregar tus cuentas bancarias, Yape, o pagos internacionales</p>
        </div>
      </div>
    </div>    <!-- Botón flotante para crear reserva -->
    <div >
        <disabledModal 
        :payMethod="selectedPayMethod" 
        :dialog="dialog == 'deshabilitar'" 
        @closeModal="hiddenModal()"
        @updateList="getPayMethod()"/>

        
    </div>
  </div>
</template> 

<style scoped>
/* Estilos adicionales si es necesario */
.badgePayMethod {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>