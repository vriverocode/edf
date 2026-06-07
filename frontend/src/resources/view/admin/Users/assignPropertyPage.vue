<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index';
import AssignUnitModal from '@/components/admin/users/modal/assignUnitModal.vue';
import moment from 'moment';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
    '_'
  ),
})

const route = useRoute();
const userStore = useUserStore();
const user = ref(null);
const ready = ref(false);
const modalRef = ref(null);
const expanded = ref(false)

const addNewUnit = (id) => {
  console.log('Pick apartment:' + id)
  // Abrir el modal correctamente usando la referencia
  if (modalRef.value) {
    modalRef.value.openModal();
  }
}

const internalNumber = (number) => {
  let splitNumber = number.split("-")
  return splitNumber[1]
}

const loadData = async () => {
  ready.value = false;
  try {
    const userId = route.params.id;
    // Usamos la función que trae al usuario con sus propiedades (Eager Loading)
    const res = await userStore.getUserById(userId);
    user.value = res.data;
  } catch (error) {
    console.error("Error al obtener datos", error);
  } finally {
    ready.value = true;
  }
};

const getTypeName = (type) => {
  const names = { 1: 'Departamento', 2: 'Estacionamiento', 3: 'Depósito' };
  return names[type] || 'Unidad';
};

onMounted(loadData);
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <div v-if="!ready" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <div v-else class="px-4 md:px-28 h-full">
        <div v-if="user.units.length > 0" class=" md:px-12 h-full">
          <div class="" style="height: 10%;">
            <div class="">
              <div class="text-subtitle1 text-bold text-black text-center">
                Unidades asignadas de: <br> {{ user.name }}
              </div>
            </div>
          </div>
          <div style="height: 80%; overflow: auto;" class="pt-2 pb-8 ">
            <q-expansion-item v-for="apartment in user.units" :key="apartment.id" class="aparmentExpand mb-4">
              <template v-slot:header>
                <q-item-section
                  class="bg-white rounded-xl w-full shadow-md border border-gray-100 overflow-hidden md:mb-5 "
                  style="position: relative;">

                  <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
                    <div class="flex justify-between items-start pl-1">
                      <span :class="'bg-' + (apartment?.status_color || 'positive')"
                        class="inline-block px-3 py-2 text-xs font-bold text-white badgeApartment"
                        style="text-transform: uppercase;">
                        {{ apartment?.status_label || 'Habitable' }}
                      </span>
                    </div>

                    <div class="flex items-center space-x-4">
                      <div class="w-16 h-16 bg-gray-200 rounded-xl flex items-center justify-center mt-2 flex-shrink-0">
                        <div v-html="iconsApp.building" style="transform: scale(0.65);" />
                      </div>

                      <div class="flex-1 space-y-1">
                        <h3 class="text-lg font-bold text-gray-900 mb-1">
                          #{{ apartment.number }}
                        </h3>
                        <div class="flex items-center text-sm text-gray-700">
                          <svg style="transform: translateX(-3px);" width="1.6rem" height="1.6rem" viewBox="0 0 64 64"
                            xmlns="http://www.w3.org/2000/svg" stroke-width="2" stroke="#374151" fill="none">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                            <g id="SVGRepo_iconCarrier">
                              <path d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z"
                                stroke-linecap="round"></path>
                              <path d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z" stroke-linecap="round">
                              </path>
                              <line x1="28.1" y1="24.86" x2="21.06" y2="24.86" stroke-linecap="round"></line>
                              <line x1="43.66" y1="32.41" x2="40.14" y2="32.41" stroke-linecap="round"></line>
                              <line x1="43.66" y1="36.9" x2="40.14" y2="36.9" stroke-linecap="round"></line>
                              <line x1="43.66" y1="41.71" x2="40.14" y2="41.71" stroke-linecap="round"></line>
                              <line x1="43.66" y1="46.19" x2="40.14" y2="46.19" stroke-linecap="round"></line>
                              <line x1="28.1" y1="30.44" x2="21.06" y2="30.44" stroke-linecap="round"></line>
                              <line x1="28.1" y1="35.94" x2="21.06" y2="35.94" stroke-linecap="round"></line>
                              <line x1="28.1" y1="41.44" x2="21.06" y2="41.44" stroke-linecap="round"></line>
                              <line x1="28.1" y1="46.94" x2="21.06" y2="46.94" stroke-linecap="round"></line>
                              <line x1="9.46" y1="52.73" x2="54.54" y2="52.73" stroke-linecap="round"></line>
                            </g>
                          </svg>

                          <span class="font-medium"> Piso {{ apartment?.floor || '--' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="py-1 px-3 bg-gray-50">
                    <div class="flex justify-between items-center">
                      <q-chip label="Solvente" color="positive" text-color="white" />
                      <div class="flex items-center">
                        <q-btn flat rounded size="sm" class="ml-3" @click="showPick(apartment.number)">
                          <q-tooltip class="bg-primary  text-white text-body2" :offset="[10, 10]">
                            Ver detalles
                          </q-tooltip>
                          <q-icon name="eva-arrow-ios-downward-outline" size="md" />
                        </q-btn>

                      </div>
                    </div>
                  </div>
                </q-item-section>
              </template>
              <q-card class="md:px-5 mt-2 shadow-md" style="border-bottom: 1px solid lightgray;">
                <q-card-section>
                  <div class=" text-center text-xl pb-2 text-stone-600 md:text-2xl  font-bold">
                    Ficha Unidad Inmobiliaria
                  </div>
                  <div class="row">
                    <div class="col-12 col-md-6">
                      <div class="flex my-2">
                        <div class="text-black font-medium">Dirección:</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.address }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Tipo:</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.type_label }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Nro interno:</div>
                        <div class="ml-1 text-black font-medium">{{ internalNumber(apartment.number) }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Número:</div>
                        <div class="ml-1 text-black font-medium">{{ internalNumber(apartment.number) }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Área ocupada (m²):</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.area }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Sector / Bloque:</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.block }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Descripcion:</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.description }}</div>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <div class="flex my-2">
                        <div class="text-black font-medium">Urbanización:</div>
                        <div class="ml-1 text-black font-medium"></div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Uso:</div>
                        <div class="ml-1 text-black font-medium"></div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Nro. part. registral:</div>
                        <div class="ml-1 text-black font-medium">0</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Área de terreno (m²):</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.area }}</div>
                      </div>
                      <div class="flex my-2">
                        <div class="text-black font-medium">Nro. Piso:</div>
                        <div class="ml-1 text-black font-medium">{{ apartment.floor }}</div>
                      </div>
                    </div>
                  </div>
                </q-card-section>
              </q-card>
            </q-expansion-item>
          </div>
          <div class="pb-4" style="height: 10%;">
            <div class="flex items-end h-full justify-center">
              <q-btn color="primary" class="full-width" style="border-radius: 0.5rem;" @click="addNewUnit()">
                <div class="px-8 py-1">
                  Asignar nueva unidad
                </div>
              </q-btn>
            </div>
          </div>
        </div>

        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-28 h-28 bg-blue-100 rounded-full flex items-center justify-center mb-6"
            v-html="iconsApp.building" />
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Aparatamento no asignado 🤔</h3>
          <p class="text-gray-600 text-center mb-6 px-8">Todavia no tienes departamentos asignado, comunicate con el
            administrador o el personal de soporte</p>
        </div>
      </div>
    </div>

    <AssignUnitModal ref="modalRef" :user-id="route.params.id" @success="loadData" />

  </div>
</template>

<style>
.aparmentExpand .q-item {
  padding: 0px;
  border-radius: 42px;
}

.aparmentExpand .q-focus-helper {
  display: none;
}

.q-item__section--side {
  display: none;
}

/* Estilos adicionales si es necesario */
.badgeApartment {
  position: absolute;
  right: 0;
  border-bottom-left-radius: 0.5rem;
  top: 0;
}
</style>