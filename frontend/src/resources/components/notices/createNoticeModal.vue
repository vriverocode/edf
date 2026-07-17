<script setup>
import { ref, watch } from 'vue';
import { useNoticeStore } from '@//services/store/notice.store';
import { useApartmentStore } from '@/services/store/apartment.store';
import { useUserStore } from '@/services/store/users.store';
import { Notify } from 'quasar';

const emit = defineEmits(['closeModal', 'updateList'])
const props = defineProps({
  dialog: Boolean,
})
const noticeStore = useNoticeStore();
const apartmentStore = useApartmentStore()
const userStore = useUserStore()

const loading = ref(false)
const dialog = ref(props.dialog)
const formData = ref({
  title:'',
  description:'',
  group: 0,
  category: 0,
  imagen:[],
})

const segmentType = ref('all')
const segmentIds = ref([])
const deptOptions = ref([])
const userOptions = ref([])
const towerInput = ref('')
const floorInput = ref('')

const segmentOptions = [
  { value: 'all', label: 'Todos los residentes' },
  { value: 'tower', label: 'Por torre' },
  { value: 'floor', label: 'Por piso' },
  { value: 'department', label: 'Por departamento' },
  { value: 'user', label: 'Por usuario' },
]

const loadDeptOptions = async () => {
  try {
    const res = await apartmentStore.getPaginationApartment({ page: 1, search: '', filter: 0, type: 0, per_page: 999 })
    const list = res?.data?.data || []
    deptOptions.value = list.map(d => ({ label: `#${d.number} — ${d.address || ''}`, value: d.id }))
  } catch {
    deptOptions.value = []
  }
}

const loadUserOptions = async () => {
  try {
    const res = await userStore.getUsers({ rol: 2, per_page: 999 })
    const list = res?.data?.data || res?.data || []
    userOptions.value = list.map(u => ({ label: `${u.name} — ${u.email}`, value: u.id }))
  } catch {
    userOptions.value = []
  }
}

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
    group: 0,
    category: 0,
    imagen:[],
  }
  segmentType.value = 'all'
  segmentIds.value = []
  towerInput.value = ''
  floorInput.value = ''
}
const createAnnounce = () => {
  loading.value = true
  const NOTICE_TYPE = 1

  const dataForm =  new FormData
  dataForm.append('title', formData.value.title)
  dataForm.append('description', formData.value.description)
  dataForm.append('group', formData.value.group)
  dataForm.append('category', formData.value.category)
  dataForm.append('type', NOTICE_TYPE)
  dataForm.append('segment_type', segmentType.value)

  if (segmentType.value === 'tower') {
    segmentIds.value = towerInput.value.split(',').map(s => s.trim()).filter(Boolean)
  } else if (segmentType.value === 'floor') {
    segmentIds.value = floorInput.value.split(',').map(s => s.trim()).filter(Boolean)
  }

  segmentIds.value.forEach((id) => {
    dataForm.append('segment_ids[]', String(id))
  })

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

const getSegmentLabel = (type) => segmentOptions.find(o => o.value === type)?.label || 'Todos'

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
  if (newValue) {
    loadDeptOptions()
    loadUserOptions()
  }
})

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
            Publicar noticia
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa md:mt-5 py-0 ">
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
                  class="form__inputsR mt-1"
                  color="primary"
                  :rules="[ val => val && val.length > 0 || 'Titulo del anuncio es obligatorio']"
                />
            </div>
            <div class="col-md-6 col-12 mt-1 px-2 md:px-12">
              <div class="text-subtitle2 text-black">
                Descripción *
              </div>
              <q-input
                dense
                borderless
                type="textarea"
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
            <div class="col-12 mt-3 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Segmentación</div>
              <q-select dense borderless v-model="segmentType" :options="segmentOptions"
                option-label="label" option-value="value" emit-value map-options
                class="form__inputsR mt-1" color="primary" />
            </div>
            <div v-if="segmentType === 'tower'" class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Torres (separadas por coma)</div>
              <q-input dense borderless v-model="towerInput" placeholder="Ej: A, B, C"
                class="form__inputsR mt-1" color="primary" />
            </div>
            <div v-if="segmentType === 'floor'" class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Pisos (separados por coma)</div>
              <q-input dense borderless v-model="floorInput" placeholder="Ej: 1, 2, 3"
                class="form__inputsR mt-1" color="primary" />
            </div>
            <div v-if="segmentType === 'department'" class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Departamentos</div>
              <q-select dense borderless v-model="segmentIds" :options="deptOptions"
                option-label="label" option-value="value" emit-value map-options multiple
                class="form__inputsR mt-1" color="primary" />
            </div>
            <div v-if="segmentType === 'user'" class="col-md-6 col-12 mt-2 px-2 md:px-12">
              <div class="text-subtitle2 text-black">Usuarios</div>
              <q-select dense borderless v-model="segmentIds" :options="userOptions"
                option-label="label" option-value="value" emit-value map-options multiple use-chips
                class="form__inputsR mt-1" color="primary" />
            </div>
          </div>
        </section>
        <section class="py-5 ">
          <!-- <div class="w-full px-4 text-caption">
            Recuerda: Para publicar, debe estar al día con tus pagos y esperar la validación del administrador.
          </div> -->
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