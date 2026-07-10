<script setup>
import { ref, watch } from 'vue';
import { useNoticeStore } from '@//services/store/notice.store';
import { Notify } from 'quasar';

const emit = defineEmits(['closeModal', 'updateList'])
const props = defineProps({
  dialog: Boolean,
})
const noticeStore = useNoticeStore();

const groups = noticeStore.group.slice(1)
const groupOptions = [{name:'Selecciona una opción', value: -1}, ...groups]
const categoryOptions = ref([{name:'Selecciona una opción', value: -1}])

const loading = ref(false)
const dialog = ref(props.dialog)
const formData = ref({
  title:'',
  description:'',
  group: {name:'Selecciona una opción', value: -1},
  category: {name:'Selecciona una opción', value: -1},
  imagen:[],
})


const hideModal = () => {
  emit('closeModal')
  cleanForm()
}
const updateList = () => {
  hideModal()
  emit('updateList')

}
const cleanForm = () => {
  formData.value = {
    title:'',
    description:'',
    group: {name:'Selecciona una opción', value: -1},
    category: {name:'Selecciona una opción', value: -1},
    imagen:[],
  }
}
const createAnnounce = () => {
  loading.value = true
  const ANNOUNCE_TYPE = 2

  const dataForm =  new FormData
  dataForm.append('title', formData.value.title)
  dataForm.append('description', formData.value.description)
  dataForm.append('group', formData.value.group)
  dataForm.append('category', formData.value.category)
  dataForm.append('type', ANNOUNCE_TYPE)

  formData.value.imagen.forEach((file) => {
    dataForm.append('img[]', file);
  })

  noticeStore.createNotice(dataForm)
  .then((data) => {
    showNotify('positive', 'Tu anuncio fue enviado para revisión')
    updateList()
  })
  .catch((response) => {
    console.log(response)
    showNotify('negative', 'Error al publicar anuncio')
  })
  .finally(() => {
    loading.value = false
  })
}
const isAvailableOption = (val) => {
  if(val == -1) {
    categoryOptions.value = [{name:'Selecciona una opción', value: -1}]
    formData.value.category = {name:'Selecciona una opción', value: -1}
    return
  }
  categoryOptions.value = [{name:'Selecciona una opción', value: -1}, ...noticeStore.category[val]]
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
const onRejected = (e) => {
  const errorMessage = e[0].failedPropValidation == 'max-files'
  ? 'Solo se pueden publicar maximo 2 fotos por post'
  :'Error al subir imagen, verifica que sea una imagen valida';
  showNotify('negative', errorMessage)
}
watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
});

</script>
<template>
  <q-dialog v-model="dialog" class="createAnnounceDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document w-full " style="border-radius:1rem">
      <q-form
        @submit="createAnnounce()"
      >
        <q-card-section class="q-px-none">
          <div class="text-h6 text-black pb-2 px-5" style="border-bottom: 1px solid lightgray;">
            Publicar anuncio
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:mt-5 py-0 ">
          <div class="px-5 mt-2 text-gray-600" style="font-size: 12px;">
            Comparte tu emprendimiento, vende un producto, ofrece un servicio o publica una oportunidad para la comunidad.
          </div>
          <div class="row w-full px-4" >
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Titulo *
              </div>
              <q-input
                  dense
                  borderless
                  clearable
                  v-model="formData.title"
                  placeholder="Ej. Tortas personalizadas para cumpleaños"
                  class="form__inputsR mt-1"
                  color="primary"
                  :rules="[ val => val && val.length > 0 || 'Titulo del anuncio es obligatorio']"
                />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                ¿Qué deseas publicar? *
              </div>
              <q-select 
                class="form__inputsR mt-1"
                v-model="formData.group"
                :options="groupOptions"
                option-label="name"
                option-value="value"
                emit-value
                map-options
                :rules="[ val => val.value != -1 || 'Grupo es obligatorio']"
                @update:model-value="isAvailableOption"
                dense borderless />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Categoria *
              </div>
              <q-select 
                class="form__inputsR mt-1"
                v-model="formData.category"
                :options="categoryOptions"
                option-label="name"
                option-value="value"
                emit-value
                map-options
                :rules="[ val => val.value != -1 || 'Categoria es obligatoria']"
                dense borderless />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Descripción *
              </div>
              <q-input
                dense
                borderless
                type="textarea"
                placeholder="Describe lo que ofreces, puedes incluir precio, horarios, ubicación o cualquier información importante."
                v-model="formData.description"
                class="form__inputsR mt-1"
                color="primary"
                :rules="[ 
                  val => (val => val && val.length > 0) || 'Descripción del anuncio es obligatorio',  
                  val => (val && val.length > 30 )|| 'Debe contener minimo 30 caracteres'
                ]"
                />
            </div>
            <div class="col-md-6 col-12 mt-2 px-2 md:px-12"> 
              <div class="text-subtitle2 text-black">
                Adjuntar imagen
              </div>
              <q-file 
                v-model="formData.imagen"  
                dense
                borderless
                clearable
                class="form__inputsR mt-1"
                color="primary"
                multiple
                :max-files="2"
                accept=".jpg,.png,.webp,.jpeg image/*"
                 @rejected="onRejected"
              >
                <template v-slot:append>
                  <q-icon name="eva-folder-add-outline" class="cursor-pointer">
                  </q-icon>
                </template>
                <template v-slot:selected >
                <div class="row items-center q-gutter-x-sm">
                  <q-icon name="eva-checkmark-circle-2-outline" color="positive" size="sm" />
                  <div>Archivo subido</div>
                </div>
              </template>
              </q-file>
            </div>
          </div>
        </section>
        <section class="py-5 ">
          <div class="w-full px-4 text-caption">
            Solo los propietarios al día con sus pagos podrán publicar. 
            Todas las publicaciones serán revisadas antes de aparecer en la comunidad.
          </div>
          <div class="flex justify-evenly mt-5">
            <q-btn label="Cerrar" unelevated class="q-mx-sm " color="primary" outline
              style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;" @click="hideModal()" />
              
            <q-btn label="Publicar" type="submit" unelevated class="q-mx-sm " color="primary" :loading="loading" 
            style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " />
          </div>
        </section>
      </q-form>
    </q-card>
  </q-dialog>
</template>
<style lang="scss">
.createAnnounceDialog{
  max-height: 95dvh;
  & .q-dialog__inner--minimized > div{
    max-height: 95dvh!important;
  }
  & .q-dialog__inner{
    padding: 0px 0.8rem;
  }
}

.form__inputsR{
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
  &.q-field--auto-height.q-field--dense.q-field--labeled .q-field__control-container{
    padding-top: 10px!important;
  }
}
@media (max-width: 780px) {
  
.form__inputsR{
  & .q-field__inner {

    padding: 0.1rem 1rem;
  }
}
}

</style>