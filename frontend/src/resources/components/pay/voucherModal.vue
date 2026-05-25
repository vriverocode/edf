<script setup>
import { ZoomImg } from "vue3-zoomer";
import { ref, watch } from 'vue';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router';


const emit = defineEmits(['closeModal'])
const authStore = useAuthStore()
const props = defineProps({
  dialog: Boolean,
  vaucher: String,
})
const router = useRouter()
const loading = ref(false)
const dialog = ref(props.dialog)
const vaucher = ref(props.vaucher)
const urlMedia = import.meta.env.VITE_LARAVEL_API_URL
const hideModal = () => {
  emit('closeModal')
}

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
});
watch(() => props.vaucher, (newValue) => {
  vaucher.value = newValue
});
</script>
<template>
  <q-dialog v-model="dialog" class="voucherDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public " style="border-radius:1rem">
      <div>
        <q-card-section class="q-px-none">
          <div class="text-h6 text-center text-black pb-2 px-5" style="border-bottom: 1px solid lightgray;">
            Vaucher de pago
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:mt-5 py-5 ">
          <q-card-section class="q-pt-none q-px-sm ">
            <div class="px-2">
              <ZoomImg :src="urlMedia + vaucher" alt="" />
            </div>
          </q-card-section>
        </section>
      </div>
      <section class="pb-5">
        <div class="flex justify-evenly mt-5">
          <q-btn label="Cerrar" unelevated class="q-mx-sm " color="primary" outline
            style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " @click="hideModal()" />
        </div>
      </section>
    </q-card>
  </q-dialog>
</template>
<style lang="scss">
.voucherDialog {
  max-height: 95dvh;
}
</style>