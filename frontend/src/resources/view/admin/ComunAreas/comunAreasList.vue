<script setup>
import { inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import { useComunAreaStore } from '@/services/store/comunArea.store';
import deleteAreaModal from '@/components/comunAreas/deleteAreaModal.vue';

const comunAreaStore = useComunAreaStore()

const page = ref(1)
const materialIcons = inject('materialIcons')
const search = ref('')
const filter = ref(0)
const lastPage = ref(1)
const ready = ref(false)
const router = useRouter()
const dialog = ref(false)

const goTo = (url) => {
  router.push(url)
}

const comunAreas = ref([])
const selectedArea = ref({})
const getComunArea = () => {
  ready.value = false;

  const data = {
    page: page.value,
    search: search.value,
    filter: filter.value,
  }

  comunAreaStore.getPaginationComunArea(data)
    .then((response) => {
      if (response.code !== 200) throw response
      comunAreas.value = response.data.data;
      lastPage.value = response.data.last_page;
      setTimeout(() => {
        ready.value = true;
      }, 1000);
    })
    .catch(() => {

    })
}
const selectArea = (id) => {
  selectedArea.value = comunAreas.value.find((area) => area.id == id)
  setTimeout(() => {
    dialog.value = true
  }, 500);
}
const hiddenModal = () => {
  dialog.value = false
}
const urlMedia = import.meta.env.VITE_LARAVEL_MEDIA_URL
onMounted(() => {
  getComunArea()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="px-4 pb-4 md:px-0 md:flex  md:justify-end md:w-6/6">
      <q-btn color="primary" unelevated class="w-full mt-5 md:mx-24 createButton " style="border-radius: 0.5rem;"
        @click="goTo('/admin/comun-area/form/add')">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Agregar area común
          </div>
        </div>
      </q-btn>
    </div>
    <div class=" pb-2 md:pb-8" style="overflow: auto; height: 88%;">
      <div class="mt-5 md:mt-0 px-2 md:mx-24  pb-5" v-if="ready">
        <!-- <div class="px-2 pt-6 md:pt-3 mt-4  apartamentContainer relative" style="" > -->
        <template v-if="comunAreas.length > 0">
          <div class="px-2 pt-3 mt-4  apartamentContainer relative" style="" v-for="comunArea in comunAreas"
            :key="comunArea.id">
            <div class="flex items-center w-full pb-3 pt-2">
              <div class="imgItem__container ">
                <img :src="urlMedia + '/images/icons/' + comunArea.icon + '.svg'" alt="">
              </div>
              <div class="px-2 infoItem">
                <div class=" flex items-center text-bold  text-black" style="font-weight: bold; font-size: 1.3rem;">
                  {{ comunArea.name }} <div class="ml-2 mt-1" style="font-weight: 500; font-size: 0.89rem;">
                    ({{ comunArea.type_label }})
                  </div>
                </div>
                <div class="mt-1 ellipsis w-full" style="font-weight: 500; font-size: 0.89rem;">
                  Costo por uso{{ if }}: {{ comunArea.price == 0 ? 'Sin reserva' : comunArea.price + ' S/.' }}
                </div>
                <div class="mt-1" style="font-weight: 500; font-size: 0.89rem;">
                  Garantia: {{ comunArea.warranty_price == 0 ? 'Sin garantia' : comunArea.warranty_price + ' S/.' }}
                </div>
                <div class="mt-1" style="font-weight: 500; font-size: 0.89rem;">
                  Capacidad: {{ comunArea.capacity }} persona(s)
                </div>


              </div>
            </div>
            <div class="flex w-full justify-end py-1" style="border-top: 1px solid lightgrey;">
              <div class="position-relative relative">
                <q-btn :icon="materialIcons.outlinedFactCheck" class="mx-1" flat color="yellow-9" round size="0.85rem"
                  @click="goTo('/admin/comun-area/bookings/' + comunArea.id + '/list')">
                  <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                    :class="'bg-black text-body2 px-2'">
                    Historial de reservas
                  </q-tooltip>
                </q-btn>
                <div style="" class="flex flex-center  countReserve-badge"
                  v-if="comunArea.bookings_to_validate_count > 0">
                  {{ comunArea.bookings_to_validate_count }}
                </div>
              </div>
              <div>
                <q-btn icon="eva-settings-outline" class="mx-1" flat color="primary" round size="0.85rem"
                  @click="goTo('/admin/comun-area/form/update/' + comunArea.id)">
                  <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                    :class="'bg-black text-body2 px-2'">
                    Editar area común
                  </q-tooltip>
                </q-btn>
              </div>
              <div>
                <q-btn icon="eva-trash-2-outline" class="mx-1" flat color="negative" round size="0.85rem"
                  @click="selectArea(comunArea.id)">
                  <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                    :class="'bg-black text-body2 px-2'">
                    Borrar area común
                  </q-tooltip>
                </q-btn>
              </div>

            </div>
            <!-- <div class="itemBadge px-8 py-1" :class="{'bg-positive':!apartment.owner, 'bg-negative':apartment.owner}"> -->
            <div class="itemBadge md:px-7 px-4 py-1 bg-positive">
              Disponible
            </div>
          </div>
          <div class="flex justify-center mt-4">
            <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
              @update:model-value="getApartment()" />
          </div>
        </template>
        <template v-else>
          <div class="flex flex-center column py-24">
            <div class="p-4" style="border-radius: 1rem; background: #0284c729;">
              <q-icon name="eva-search-outline" size="3rem" color="primary" />
            </div>
            <div class="text-h6 text-primary mt-2">
              No se encontraron areas comúnes
            </div>
          </div>
        </template>
      </div>
      <div v-else class="flex flex-center py-24">
        <q-spinner-dots color="primary" size="7rem" />
      </div>
    </div>
    <template v-if="Object.values(selectedArea).length > 0">
      <deleteAreaModal :comunArea="selectedArea" :dialog="dialog" @closeModal="hiddenModal()"
        @updateList="getComunArea()" />
    </template>
  </div>
</template>
<style lang="scss">
.countReserve-badge {
  background: red;
  color: white;
  height: 1.2rem;
  width: 1.2rem;
  border-radius: 2rem;
  position: absolute;
  top: 0;
  right: 5px;
  font-size: 0.7rem;
}

.itemBadge {
  color: white;
  font-size: 0.9rem;
  top: 0;
  right: 0;
  position: absolute;
  border-bottom-left-radius: 0.8rem;
  // transform: rotate(45deg);
}

/*.imgItem__container {
  width: 4.2rem;
  height: 4.2rem;
  border-radius: 0.8rem;
  background: $primary;
}*/

.infoItem {
  width: calc(100% - 4.2rem);
}

.apartamentContainer {
  overflow: hidden;
  border-radius: .5rem;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}

.createButton {
  width: auto;
}

.tabItem {
  opacity: 0.5;
  cursor: pointer;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: all 0.2s ease-out;

  &:hover {
    background: #279edb !important;
  }

  &.active {
    opacity: 1;
  }

  &.leftItem {
    border-top-left-radius: 0.7rem;
    border-bottom-left-radius: 0.7rem;

  }

  &.rightItem {
    border-top-right-radius: 0.7rem;
    border-bottom-right-radius: 0.7rem;

  }
}

.imgItem__container {
  border-radius: 0.8rem;
  overflow: hidden;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  // border: 2px solid rgb(3, 156, 195) ;
  width: 4.2rem;
  height: 4.2rem;
  box-shadow: 0px 0.1rem 1rem 0px rgba(0, 0, 0, 0.205);
  background-repeat: no-repeat;
  background-size: cover;
  background-color: #2d6fb5;
  transition: all 0.7s ease-in-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.03);
  }

  & img {
    height: 90%;
  }
}

@media (max-width: 780px) {
  .imgItem__container {
    width: 4.2rem;
    height: 4.2rem;

  }

  .createButton {
    width: 100%;
  }
}
</style>
