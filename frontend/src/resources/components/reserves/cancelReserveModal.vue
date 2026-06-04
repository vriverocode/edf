<script setup>
import { Notify } from 'quasar'
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useReserveStore } from '@/services/store/reserve.store';


const emit = defineEmits(['closeModal', 'updateList'])
const reserveStore = useReserveStore()
const props = defineProps({
  dialog: Boolean,
  reserve: Object
})
const router = useRouter()
const loading = ref(false)
const dialog = ref(props.dialog)
const motive = ref('')
const hideModal = () => {
  emit('closeModal')
}
const updateList = () => {
  emit('closeModal')
  emit('updateList')
}

const cancelBooking = () => {
  loading.value = true
  reserveStore.cancelReserve(props.reserve.id, motive.value)
    .then((response) => {
      loading.value = false
      updateList()
    })
    .catch(() => {
      loading.value = false
      showNotify('negative', 'Error al borrar area común')
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
            Cancelar reservación
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:mt-5 mt-0 ">
          <q-card-section class="q-pt-none q-px-sm ">
            <div class="px-2">
              <div class="text-h6 text-center text-black md:px-8">
                ¿Seguro que desea cancelar la reserva <b class="text-primary">"#{{ reserve.booking_number }}"</b> en <b
                  class="text-primary">{{ reserve.comun_area.name }}</b>?
              </div>
              <div v-if="reserve.amount > 0" class="mt-2 text-grey-7  text-center">
                Los pagos realizado seran devuelto dependiendo de las politicas de reserva
              </div>

              <div class="mt-2">
                <div>
                  Motivo de cancelación:
                </div>
                <q-input v-model="motive" outlined type="textarea" class="form__inputsRs" />

              </div>
            </div>
          </q-card-section>
        </section>
      </div>
      <section class="pb-5" style="border-top: 1px solid lightgray;">
        <div class="flex justify-evenly mt-5">
          <q-btn label="No" unelevated class="q-mx-sm " color="primary" outline
            style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " @click="hideModal()" />
          <q-btn label="Si" unelevated class="q-mx-sm " color="primary" outline
            style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " :loading="loading"
            @click="cancelBooking()" />
        </div>
      </section>

    </q-card>
  </q-dialog>
</template>
<style lang="scss">
.statusInput.q-field--auto-height.q-field--labeled .q-field__control-container {
  padding-top: 10px;
}

.createPayMethodDialog {
  margin-left: 0%;
  overflow: hidden;
  overflow: visible !important;
  position: relative;

  & .dialog_document {
    max-width: 90%;
    border-radius: 1rem !important;
    // height: 100%;

  }

  & .q-dialog__inner--minimized {
    padding: 0px;
  }
}

.order__form {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.content__modalSectionRifa {
  overflow: auto;
  max-height: max-content;

}

.q-item__label {

  color: black !important;
}

.q-item--active {
  & .q-item__label {

    color: goldenrod !important;
  }
}
</style>
