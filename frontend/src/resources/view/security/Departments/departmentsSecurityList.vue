<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import { useApartmentStore } from '@/services/store/apartment.store';

const apartmentStore = useApartmentStore()

const page = ref(1)
const lastPage = ref(1)
const ready = ref(false)
const router = useRouter()

const apartments = ref([])
const searchNumber = ref('')
const searchName = ref('')
const searchTimeout = ref(null)

const getApartment = () => {
  ready.value = false;
  apartmentStore.getInhabitedDepartments(page.value, { number: searchNumber.value, name: searchName.value })
    .then((response) => {
      apartments.value = response.data.data;
      lastPage.value = response.data.last_page;
      ready.value = true;
    })
    .catch((e) => {
      console.error(e);
      ready.value = true;
    });
}

const onSearchInput = () => {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    page.value = 1
    getApartment()
  }, 400)
}

const goToResidentsInfo = (apartmentId) => {
  router.push('/security/department/' + apartmentId + '/residents')
}

onMounted(() => { getApartment() })
</script>

<template>
  <div class="h-full" style="overflow: auto;">
    <div class="mt-5 md:mt-8 px-2 md:px-28 pb-5" style="overflow: auto;" v-if="ready">
      <div class="flex flex-col md:flex-row gap-2 pb-4">
        <q-input v-model="searchNumber" outlined dense color="primary" clearable
          placeholder="Buscar por # de departamento" class="w-full md:w-1/2" @update:model-value="onSearchInput">
          <template v-slot:prepend>
            <q-icon name="eva-search-outline" color="grey-6" />
          </template>
        </q-input>
        <q-input v-model="searchName" outlined dense color="primary" clearable
          placeholder="Buscar por nombre del usuario" class="w-full md:w-1/2" @update:model-value="onSearchInput">
          <template v-slot:prepend>
            <q-icon name="eva-people-outline" color="grey-6" />
          </template>
        </q-input>
      </div>
      <template v-if="apartments.length > 0">
        <div class="px-2 pt-3 mt-4 apartamentContainer relative" v-for="apartment in apartments" :key="apartment.id">
          <div class="flex items-center w-full pb-3">
            <div class="imgItem__container w">
              <div v-html="iconsApp.apartment" class="flex flex-center px-0 h-full" />
            </div>
            <div class="px-2 infoItem">
              <div class="text-bold text-black"
                style="font-weight: bold; font-size: 1.3rem; text-transform: uppercase;">
                #{{ apartment.number }}
              </div>
              <div class="mt-1 ellipsis w-full" style="font-weight: 500; font-size: 0.89rem;">
                Ubicación: {{ apartment.address }}
              </div>
            </div>
          </div>
          <div class="flex w-full justify-end py-1" style="border-top: 1px solid lightgrey;">
            <div>
              <q-btn icon="eva-people-outline" class="mx-1" flat color="primary" round size="0.85rem"
                @click="goToResidentsInfo(apartment.id)">
                <q-tooltip class="bg-primary text-white text-body2" :offset="[10, 10]">
                  Ver residentes
                </q-tooltip>
              </q-btn>
            </div>
          </div>
          <div class="itemBadge px-8 py-1 bg-negative">
            Ocupado
          </div>
        </div>
        <div class="flex justify-center mt-4">
          <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
            @update:model-value="getApartment()" />
        </div>
      </template>
      <template v-else>
        <div class="h-full w-full font-medium text-center pt-20 text-h6">
          No hay departamentos habitados 😕
        </div>
      </template>
    </div>
    <div v-else class="flex flex-center py-24">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss">
.itemBadge {
  color: white;
  font-size: 0.9rem;
  top: 0;
  right: 0;
  position: absolute;
  border-bottom-left-radius: 0.8rem;
}

.imgItem__container {
  width: 4.2rem;
  height: 4.2rem;
  border-radius: 0.8rem;
  background: $primary;
}

.infoItem {
  width: calc(100% - 4.2rem);
}

.apartamentContainer {
  overflow: hidden;
  border-radius: .5rem;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}
</style>
