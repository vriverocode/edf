<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useApartmentStore } from '@/services/store/apartment.store';

const apartmentStore = useApartmentStore()
const route = useRoute()
const router = useRouter()
const ready = ref(false)
const residents = ref([])

const getResidents = () => {
    ready.value = false;
    apartmentStore.getDepartmentResidents(route.params.id)
        .then((response) => {
            residents.value = response.data;
            ready.value = true;
        })
        .catch((e) => {
            console.log(e);
            ready.value = true;
        });
}

const getBadgeColor = (typeId) => {
    if (typeId === 2) return 'primary'; // Propietario
    if (typeId === 5) return 'purple'; // Airbnb
    return 'positive'; // Residente / Inquilino / Familiar
}

onMounted(() => { getResidents() })
</script>

<template>
  <div class="h-full" style="overflow: auto;">
    <div class="px-4 md:px-28 mt-4 flex flex-center">
        <div class="text-h6 font-bold ml-2">Residentes del Departamento</div>
    </div>

    <div class="mt-4 md:mt-6 px-4  md:px-28 pb-5" style="overflow: auto;" v-if="ready">
      <template v-if="residents.length > 0">
        <div class="px-4 py-3 mt-3 residentContainer relative flex items-center bg-white" v-for="(resident, idx) in residents" :key="idx">
          <div class="flex items-center w-full">
            <div
              style="height: 3.5rem; width: 3.5rem; background: #1976d2; border-radius: 50%; font-size: 1.5rem; font-weight: bold;"
              class="flex flex-center text-white shadow-sm">
              {{ resident.name?.charAt(0)?.toUpperCase() || '?' }}
            </div>
            <div class="pl-4 pr-2 infoItem">
              <div class="text-bold text-black" style="font-weight: 600; font-size: 1.1rem;">
                {{ resident.name }} {{ resident.lastname }}
              </div>
              <div class="mt-1 text-grey-8" style="font-size: 0.9rem;">
                DNI: {{ resident.dni || 'No registrado' }}
              </div>
              <div class="mt-1 text-grey-7" style="font-size: 0.9rem;">
                Tlf: {{ resident.phone || 'No registrado' }}
              </div>
            </div>
          </div>
          <div class="itemBadge px-3 py-1" :class="`bg-${getBadgeColor(resident.type_id)}`">
            {{ resident.type_label }}
          </div>
        </div>
      </template>
      <template v-else>
        <div class="h-full w-full font-medium text-center pt-20 text-h6">
          No hay residentes registrados 😕
        </div>
      </template>
    </div>
    <div v-else class="flex flex-center py-24">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss" scoped>
.itemBadge {
  color: white;
  font-size: 0.8rem;
  font-weight: bold;
  top: 0;
  right: 0;
  position: absolute;
  border-bottom-left-radius: 0.8rem;
}

.infoItem {
  width: calc(100% - 3.5rem);
}

.residentContainer {
  overflow: hidden;
  border-radius: 0.8rem;
  border: 1px solid #e0e0e0;
  box-shadow: 0px 2px 6px 0px rgba(0,0,0,0.05);
}
</style>
