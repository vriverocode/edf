<script setup>
import { ref, onMounted } from 'vue';
import { useEventStore } from '@/services/store/event.store';
import { useRouter } from 'vue-router';
import eventsList from '@/components/events/eventsList.vue';
import deleteEventModal from '@/components/events/deleteEventModal.vue';
const events = ref([]);
const loading = ref(false);
const eventStore = useEventStore();
const router = useRouter();
const dialog = ref('');
const selectedEvent = ref({})
const getEvents = () => {
  loading.value = true;
  eventStore.getEvents()
    .then((response) => {
      if (response.code !== 200) throw response;
      events.value = response.data;
    })
    .catch((response) => {
      console.error(response);
    })
    .finally(() => {
      loading.value = false;
    });
}
const selectEvent = (id) => {
  selectedEvent.value = events.value.find(event => event.id == id)
  showDialog('delete')
}
const closeModal = () => {
  dialog.value = '';
  selectedEvent.value = {};
}
const showDialog = (dialogType) => {
  dialog.value = dialogType
}
const goTo = (url) => {
  router.push(url);
}



onMounted(() => {
  getEvents();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <div class="px-4 flex  justify-center items-center md:w-full md:px-12" style="height: 12%;">
      <q-btn color="primary" unelevated class="w-full mt-0 md:mx-24 createBookingButton md:w-full"
        style="border-radius: 0.5rem; width: 100%;" @click="goTo('/admin/events/form/add')">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Agregar Evento
          </div>
        </div>
      </q-btn>
    </div>
    <div class="" style="height: 88%; overflow: auto;">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <!-- <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div> -->
        <q-spinner-dots color="primary" size="7rem" />

      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <eventsList :events="events" @selectEvent="selectEvent"/>
      </div>
    </div>
    <!-- Botón flotante para crear reserva -->
    
    <template v-if="Object.values(selectedEvent).length > 0">
      <deleteEventModal :dialog="(dialog === 'delete')" :event="selectedEvent" @closeModal="closeModal" @updateList="getEvents"/>
    </template>
  </div>
</template>
