<script setup>
import { ref, watch } from 'vue';
import { useNoticeStore } from '@//services/store/notice.store';
import { Notify } from 'quasar';

const emit = defineEmits(['closeModal', 'updateList'])
const props = defineProps({
  dialog: Boolean,
  announce: Object
})
const noticeStore = useNoticeStore();
const loading = ref(false)
const dialog = ref(props.dialog)

const hideModal = () => {
  emit('closeModal')
}
const updateList = () => {
  hideModal()
  emit('updateList')

}
const deleteAnnounce = () => {
  loading.value = true
  noticeStore.deleteNotices(props.announce.id)
  .then((data) => {
    showNotify('grey-5', 'El anuncio fue eliminado')
    updateList()
  })
  .catch((response) => {
    console.error(response)
    showNotify('negative', 'Error al borrar anuncio')
  })
  .finally(() => {
    loading.value = false
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
  <q-dialog v-model="dialog" class="deleteAnnounceDialog" persistent  backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document w-full " style="border-radius:1rem">
      <div class="px-5 py-4">
        <div class="my-5 text-center text-lg" style="line-height: 1.7;">
          ¿Seguro que deseas borrar el anuncio: <br><b>{{ announce.title }}</b> ? 
        </div>
        <section class="mt-4 flex justify-between px-2">
          <q-btn color="primary" label="Cerrar" @click="hideModal"/>
          <q-btn color="primary" outline="" label="Borrar" @click="deleteAnnounce" :loading="loading"/>
        </section>
      </div>
    </q-card>
    
  </q-dialog>
</template>
<style lang="scss">
.deleteAnnounceDialog{
  max-height: 95dvh;
  & .q-dialog__inner--minimized > div{
    max-height: 95dvh!important;
  }
  & .q-dialog__inner{
    padding: 0px 0.8rem;
  }
}



</style>