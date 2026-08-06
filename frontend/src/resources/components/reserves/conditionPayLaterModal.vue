<script setup>
import { ref, watch } from 'vue';

const emit = defineEmits(['closeModal', 'confirmPayLater']);

const props = defineProps({
  dialog: Boolean
});

const dialog = ref(props.dialog);

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue;
});

const hideModal = () => {
  emit('closeModal');
};

// Esta función se ejecuta al darle al botón "Sí"
const confirmAction = () => {
  // Le avisamos al padre (createReserve) que el usuario aceptó las condiciones
  emit('confirmPayLater');
};
</script>

<template>
  <q-dialog v-model="dialog" class="createPayMethodDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public" style="border-radius:1rem; padding: 20px;">
        <q-card-section class="q-px-none">
            <div class="text-h6 text-center text-black pb-2" style="border-bottom: 1px solid lightgray;">
            Posponer pago de reserva
        </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:pt-5 md:pb-5 pt-1 pb-4  ">
        <div class="text-subtitle1 md:px-5" style="font-weight: 500;">
            Puedes realizar el pago más adelante, siempre que se efectúe al menos 72 horas antes de la fecha de la reserva.<br>
            Si el pago no se registra dentro del plazo establecido, la reserva será cancelada automáticamente.
        </div>
        </section>
        <section class="pb-0" style="border-top: 1px solid lightgray;">
            <div class="row mt-4 ">
              <div class="col-6 flex-center flex">
                <q-btn label="No, volver" unelevated class="q-mx-sm" color="grey-7" outline
                    style="border-radius: 0.8rem; padding:0.5rem 1.5rem!important;" @click="hideModal" />
              </div>
              <div class="col-6 flex-center flex">
                <q-btn label="Sí, acepto" unelevated class="q-mx-sm" color="primary"
                    style="border-radius: 0.8rem; padding:0.5rem 1.5rem!important;" @click="confirmAction" />
              </div>
            </div>
        </section>

    </q-card>
  </q-dialog>
</template>