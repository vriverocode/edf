<script setup>
import { ref, onMounted } from 'vue';
import { Switch, showToast } from 'vant';
import iconsApp from '@/assets/icons/index'
import { useApartmentStore } from '@/services/store/apartment.store'
import moment from 'moment';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@//services/store/auth.services';
moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split(
    '_'
  ),
})
const { user } = storeToRefs(useAuthStore())
const apartmentStore = useApartmentStore()
const ready = ref(false)
const apartments = ref([])
const updatingId = ref(null)

const getApartaments = () => {
  apartmentStore.getApartmentByUser()
    .then((data) => {
      apartments.value = data.data
    })
    .catch((response) => {

    })
    .finally(() => {
      ready.value = true
    })
}

const toggleTenantPaysQuota = async (apartment) => {
  updatingId.value = apartment.id
  try {
    const newValue = !apartment.tenant_pays_quota
    await apartmentStore.updateApartment(apartment.id, {
      ...apartment,
      tenant_pays_quota: newValue,
    })
    apartment.tenant_pays_quota = newValue
    showToast({
      message: newValue
        ? 'El inquilino será responsable del pago'
        : 'El propietario será responsable del pago',
      position: 'bottom',
    })
  } catch (e) {
    showToast({ message: 'Error al actualizar', position: 'bottom', type: 'fail' })
  } finally {
    updatingId.value = null
  }
}

const expanded = ref(false)
const showPick = (id) => {
  console.error('Pick apartment:' + id)
}
const internalNumber = (number) => {
  let splitNumber = number.split("-")
  return splitNumber[1]
}
onMounted(() => {
  getApartaments()
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="" style="height: 100%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="!ready" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />

      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <div v-if="apartments.length > 0" class="space-y-3 md:px-12">
          <q-expansion-item v-for="apartment in apartments" :key="apartment.id" class="aparmentExpand">
            <template v-slot:header>
              <q-item-section
                class="bg-white rounded-xl w-full shadow-md border border-gray-100 overflow-hidden md:mb-5 "
                style="position: relative;">

                <!-- Sección superior - Detalles de la reserva -->
                <div class="px-4 pb-4 pt-2 border-b border-dashed border-gray-300">
                  <!-- Header con nombre y estado -->
                  <div class="flex justify-between items-start pl-1">
                    <!-- Estado badge -->
                    <span :class="'bg-' + (apartment?.status_color || 'primary')"
                      class="inline-block px-3 py-2 text-xs font-bold text-white badgeApartment">
                      {{ apartment?.type_label || '---' }}
                    </span>
                  </div>

                  <!-- Contenido principal con imagen y detalles -->
                  <div class="flex items-center space-x-4">
                    <!-- Imagen del área -->
                    <div class="w-16 h-16 bg-gray-200 rounded-xl flex items-center justify-center mt-2 flex-shrink-0">
                      <div v-html="iconsApp.building" style="transform: scale(0.65);" />
                    </div>

                    <!-- Detalles de la reserva -->
                    <div class="flex-1 space-y-1">
                      <h3 class="text-lg font-bold text-gray-900 mb-1" style="text-transform: uppercase;">
                        #{{ apartment.number }}
                      </h3>
                      <!-- piso -->
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

                        <span class="font-medium"> Piso {{ apartment.floor }}</span>
                      </div>

                      <!-- Propietario -->
                      <div class="flex items-center text-sm text-gray-700">
                        <svg width="1rem" height="1rem" viewBox="0 0 24 24" class="mr-2" fill="none"
                          xmlns="http://www.w3.org/2000/svg">
                          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                          <g id="SVGRepo_iconCarrier">
                            <path
                              d="M5 21C5 17.134 8.13401 14 12 14C15.866 14 19 17.134 19 21M16 7C16 9.20914 14.2091 11 12 11C9.79086 11 8 9.20914 8 7C8 4.79086 9.79086 3 12 3C14.2091 3 16 4.79086 16 7Z"
                              stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                          </g>
                        </svg>
                        <span class="font-medium">
                          {{ apartment?.owner?.name || 'S/N' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Sección inferior - Estado de pago -->
                <div class="py-1 px-3 bg-gray-50">
                  <div class="flex justify-between items-center">
                    <q-chip :label="apartment.due_quotas.length > 0 ? 'Moroso':'Al día'" color="positive" text-color="white" />
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
            <q-card class="md:px-5 mt-2 shadow-md" style="border-radius: 1rem; box-shadow: #7b7b7b38 0px 5px 10px 0px;">
              <q-card-section>
                <div class=" text-center text-xl pb-2 text-stone-600 md:text-2xl  font-bold">
                  Información del departamento
                </div>
                <div class="row">
                  <div class="col-12 col-md-6">
                    <!-- <div class="flex my-2">
                      <div class="text-black font-medium">Dirección:</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.address }}</div>
                    </div> -->
                    <div class="flex my-2">
                      <div class="text-black font-medium">Tipo:</div>
                      <div class="ml-1 text-black font-medium">{{ 'Departamento' }}</div>
                    </div>
                    <!-- <div class="flex my-2">
                      <div class="text-black font-medium">Nro. interno:</div>
                      <div class="ml-1 text-black font-medium">{{ internalNumber(apartment.number) }}</div>
                    </div> -->
                    <div class="flex my-2">
                      <div class="text-black font-medium">Número:</div>
                      <div class="ml-1 text-black font-medium">{{ internalNumber(apartment.number) }}</div>
                    </div>
                    <div class="flex my-2">
                      <div class="text-black font-medium">Área ocupada (m²):</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.area }}</div>
                    </div>
                    <!-- <div class="flex my-2">
                      <div class="text-black font-medium">Sector / Bloque:</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.block }}</div>
                    </div> -->
                    <div class="flex my-2">
                      <div class="text-black font-medium">Propietario:</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.owner.name }}</div>
                    </div>
                    <div class="flex my-2">
                      <div class="text-black font-medium">Responsable de la unidad:</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.owner.name }}</div>
                    </div>
                    <div class="flex items-center my-3 p-3 bg-gray-50 rounded-lg">
                      <div class="flex-1">
                        <div class="text-black font-medium text-sm">¿Quién paga la cuota?</div>
                        <div class="text-gray-500 text-xs mt-0.5">
                          {{ apartment.tenant_pays_quota ? 'El inquilino es responsable del pago' : 'El propietario es responsable del pago' }}
                        </div>
                      </div>
                      <van-switch
                        :model-value="apartment.tenant_pays_quota"
                        :loading="updatingId === apartment.id"
                        size="24px"
                        @change="toggleTenantPaysQuota(apartment)"
                      />
                    </div>
                    <div class="flex my-2">
                      <div class="text-black font-medium">Descripción:</div>
                      <div class="ml-1 text-black font-medium">{{ apartment.description }}</div>
                    </div>
                  </div>
                  <div class="col-12 col-md-6">
                    <!-- <div class="flex my-2">
                      <div class="text-black font-medium">Urbanización:</div>
                      <div class="ml-1 text-black font-medium"></div>
                    </div>
                    <div class="flex my-2">
                      <div class="text-black font-medium">Uso:</div>
                      <div class="ml-1 text-black font-medium"></div>
                    </div> -->
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
                      <div class="ml-1 text-black font-medium">{{ apartment.floor}}</div>
                    </div>
                  </div>
                </div>
              </q-card-section>
            </q-card>
          </q-expansion-item>
        </div>

        <!-- Estado vacío -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-28 h-28 bg-blue-100 rounded-full flex items-center justify-center mb-6"
            v-html="iconsApp.building" />
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Aparatamento no asignado 🤔</h3>
          <p class="text-gray-600 text-center mb-6 px-8">Todavia no tienes departamentos asignado, comunicate con el
            administrador o el personal de soporte</p>

        </div>
      </div>
    </div>

  </div>
</template>

<style>
.aparmentExpand .q-item {
  padding: 0px;
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