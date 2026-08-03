<script setup>
import { Notify } from 'quasar'
import { ref, watch, computed } from 'vue';
import { useComunAreaStore } from '@/services/store/comunArea.store';

const emit = defineEmits(['closeModal', 'updateList'])
const comunAreaStore = useComunAreaStore()
const props = defineProps({
  dialog: Boolean,
  comunArea: Object
})
const loading = ref(false)
const dialog = ref(props.dialog)
const hideModal = () => {
  emit('closeModal')
}
const updateList = () => {
  emit('closeModal')
  emit('updateList')
}

const isEnabled = computed(() => !!props.comunArea.status)

const toggleArea = () => {
  loading.value = true
  comunAreaStore.toggleComunAreaStatus(props.comunArea.id)
    .then(() => {
      loading.value = false
      showNotify('positive', isEnabled.value ? 'Área común deshabilitada' : 'Área común habilitada')
      updateList()
    })
    .catch((err) => {
      loading.value = false
      showNotify('negative', err || 'Error al cambiar el estado del área común')
    })
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
});
</script>
<template>
  <q-dialog v-model="dialog" class="createPayMethodDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public" style="border-radius:1rem">
      <div>
        <q-card-section class="q-px-none">
          <div class="text-h6 text-center text-black pb-2" style="border-bottom: 1px solid lightgray;">
            {{ isEnabled ? 'Deshabilitar área común' : 'Habilitar área común' }}
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:mt-5 mt-0">
          <q-card-section class="q-pt-none q-px-sm">
            <div class="px-2">
              <div class="flex flex-center mb-3">
                <q-icon name="eva-power-outline" size="3rem" :color="isEnabled ? 'negative' : 'positive'" />
              </div>
              <div class="text-h6 text-center text-black" v-if="isEnabled">
                ¿Seguro de <b>deshabilitar</b> <b>"{{ comunArea.name }}"</b>? Los residentes ya no podrán reservarla.
              </div>
              <div class="text-h6 text-center text-black" v-else>
                ¿Seguro de <b>habilitar</b> <b>"{{ comunArea.name }}"</b>? Los residentes podrán reservarla nuevamente.
              </div>
            </div>
          </q-card-section>
        </section>
      </div>
      <section class="pb-5">
        <div class="flex justify-evenly mt-5">
          <q-btn label="No" unelevated class="q-mx-sm" color="primary" outline
            style="border-radius: 0.8rem; padding:0px 2rem!important; font-size: 1rem;" @click="hideModal()" />
          <q-btn :label="isEnabled ? 'Deshabilitar' : 'Habilitar'" unelevated class="q-mx-sm"
            :color="isEnabled ? 'negative' : 'positive'"
            style="border-radius: 0.8rem; padding:0px 2rem!important; font-size: 1rem;" :loading="loading"
            @click="toggleArea()" />
        </div>
      </section>
    </q-card>
  </q-dialog>
</template>
<style lang="scss">
.createPayMethodDialog {
  margin-left: 0%;
  overflow: hidden;
  overflow: visible !important;
  position: relative;

  & .dialog_document {
    max-width: 90%;
    border-radius: 1rem !important;
  }

  & .q-dialog__inner--minimized {
    padding: 0px;
  }
}

.content__modalSectionRifa {
  overflow: auto;
  max-height: max-content;
}
</style>
