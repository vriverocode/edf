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
        <section class="content__modalSectionRifa md:mt-5 mt-0 ">
        <div>
            El pago puede posponerse, pero debe realizarse con 24 horas
            de antelación a la fecha de la reserva. En caso de no recibir el pago, la reserva será cancelada.
        </div>
        </section>
        <section class="pb-2" style="border-top: 1px solid lightgray;">
            <div class="flex justify-evenly mt-4">
            <q-btn label="No, volver" unelevated class="q-mx-sm" color="grey-7" outline
                style="border-radius: 0.8rem; padding:0px 2rem!important;" @click="hideModal" />
                
            <q-btn label="Sí, acepto" unelevated class="q-mx-sm" color="primary"
                style="border-radius: 0.8rem; padding:0px 2rem!important;" @click="confirmAction" />
            </div>
        </section>

    </q-card>
  </q-dialog>
</template>