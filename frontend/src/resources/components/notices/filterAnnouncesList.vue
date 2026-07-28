<script setup>
import { onMounted, ref, watch } from 'vue';
import { RadioGroup, Radio } from 'vant';
import { useNoticeStore } from '@/services/store/notice.store';
import { useAuthStore } from '@/services/store/auth.services';
import { storeToRefs } from 'pinia';

const { user } = storeToRefs(useAuthStore())
const props = defineProps({
  dialog: Boolean,
  typeSearch: String,
  isOnlyMyPost: {
    type: String,
    required: false
  }
})
const emit = defineEmits(['closeModal', 'updateList'])

const typeOfSearch = ref(props.typeSearch)
const noticeStore = useNoticeStore()
const groups = noticeStore.group.slice(1)
const groupOptions = ref(typeOfSearch.value == 'announces' 
? [{name:'Selecciona una opción', value: -1}, ...groups] 
: [{name:'Selecciona una opción', value: -1}, noticeStore.group[0]])

const categoryOptions = ref([{name:'Selecciona una opción', value: -1}])
const userOptions = ref([{name:'Selecciona una opción', id: -1}])
const loading = ref(false)
const dialog  = ref(props.dialog)

const filters = ref({
  status: 4,
  group: {name:'Selecciona una opción', value: -1},
  category: {name:'Selecciona una opción', value: -1},
  post_by:-1,
  date_from: '',
  date_to: '',
  only_my_posts: '',
})

watch(() => props.isOnlyMyPost, (val) => {
  if (val !== undefined) filters.value.only_my_posts = val
}, { immediate: true })

const hideModal = () => {
  emit('closeModal')
}

const getUserOptions = () => {
  noticeStore.getUserWithPublish()
  .then((response) => {
    userOptions.value = [...userOptions.value, ...response.data]
  })
  .catch((response) => {
    console.error(response)
  })
}
const updateList = () => {
  emit('closeModal')
  emit('updateList', { ...formatFilters() })
}
const formatFilters = () => {

  return {
    status: filters.value.status,
    group: filters.value.group.value == -1 ? '' : filters.value.group.value,
    category: filters.value.category.value == -1 ? '' : filters.value.category.value,
    post_by:  filters.value.post_by == -1 ? '' : filters.value.post_by,
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
    only_my_posts: filters.value.only_my_posts,
  }
}
const isAvailableOption = (val) => {
  if(val.value == -1) {
    categoryOptions.value = [{name:'Selecciona una opción', value: -1}]
    filters.value.category = {name:'Selecciona una opción', value: -1}
    return
  }
  categoryOptions.value = [{name:'Selecciona una opción', value: -1}, ...noticeStore.category[val.value]]
}
const resetFilters = () => {
  filters.value = {
    status: 4,
    group: {name:'Selecciona una opción', value: -1},
    category: {name:'Selecciona una opción', value: -1},
    post_by:-1,
    date_from: '',
    date_to: '',
    only_my_posts: '',
  }
  categoryOptions.value = [{name:'Selecciona una opción', value: -1}]

  emit('updateList', { ...formatFilters() })

}
onMounted(() => {
  getUserOptions()
})

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
});
watch(() => props.typeSearch, (newValue) => {
  typeOfSearch.value = newValue
  groupOptions.value = typeOfSearch.value == 'announces' 
  ? [{name:'Selecciona una opción', value: -1}, ...groups] 
  : [{name:'Selecciona una opción', value: -1}, noticeStore.group[0]]
});

</script>
<template>
  <q-dialog v-model="dialog" class="filterNoticeDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public py-2 ">
      <div class=" header-sectionModal " style="border-bottom: 1px solid lightgray;">
        <div class="flex justify-between items-center pr-5 pl-2 py-2" >
          <q-btn round outline icon="eva-arrow-back-outline" color="primary"  @click="resetFilters(), hideModal() "/>
          <div class="text-2xl text-primary font-bold pt-1">
            Filtrar
          </div>
        </div>
      </div>
      <div class="content-sectionModal">
        <!-- <section class="content__modalSectionRifa md:mt-0 mt-0  py-2" v-if="(isOnlyMyPost && isOnlyMyPost == 'active') || user.rol_id == 1"> -->
        <section class="content__modalSectionRifa md:mt-0 mt-0  py-2" >

          <div class="row pt-3 pb-2 md:px-5 px-3">
            <div class="mb-1 text-lg font-medium text-primary">
              Estado de anuncios/noticias
            </div>
            <div class="col-12">
              <radio-group v-model="filters.status" class="row statusRadioGroup">
                <div class="col-12 my-2" >
                  <radio :name="4" icon-size="1.3rem" label-position="left">Todas</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="1" icon-size="1.3rem" label-position="left">Pendiente</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="2" icon-size="1.3rem" label-position="left">Aprobada</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="0" icon-size="1.3rem" label-position="left">Cancelado</radio>
                </div>  
              </radio-group>
            </div>
            
          </div>
          <div class="row py-4 md:px-5 px-3 pb-5" style="border-top: 1px solid lightgray;" >
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Grupo
            </div>
            <div class="col-12 ">
              <q-select 
                class="form__inputsFilterPays"
                v-model="filters.group"
                @update:model-value="isAvailableOption"
                :options="groupOptions"
                option-label="name"
                option-value="value"
                dense borderless />
            </div>
          </div>
          <div class="row py-4 md:px-5 px-3 pb-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Categoria
            </div>
            <div class="col-12 ">
              <q-select 
              class="form__inputsFilterPays"
              v-model="filters.category"
              :options="categoryOptions"
              option-label="name"
              option-value="value"
              emit-value
              map-options
              dense borderless />
            </div>
            
          </div>
          <div class="row py-4 md:px-5 px-3 pb-2" style="border-top: 1px solid lightgray;" v-if="typeOfSearch == 'announces'">
            <div class="col-12 flex items-center">
              <q-toggle v-model="filters.only_my_posts" true-value="active" false-value="" label="Solo mis anuncios" color="primary" />
            </div>
          </div>
          <!-- <div class="row py-4 md:px-5 px-3 pb-5" style="border-top: 1px solid lightgray;" v-if="typeOfSearch == 'announces'">
            <div class="mb-4 text-lg font-medium text-primary col-12">
              Publicado por
            </div>
            <div class="col-12 px-1">
              <q-select 
                class="form__inputsFilterPays"
                v-model="filters.post_by"
                :options="userOptions"
                option-label="name"
                option-value="id"
                emit-value
                map-options
                dense borderless />
            </div>
          </div> -->
          <div class="row py-4 md:px-5 px-3 pb-5" style="border-top: 1px solid lightgray;">
            <div class="mb-4 text-lg font-medium text-primary col-12">
              Rango de fechas de pago
            </div>
            <div class="col-6 pr-1">
              <q-input class="form__inputsFilterPays" dense borderless v-model="filters.date_from" type="date" label="Desde" />
            </div>
            <div class="col-6 pl-1">
              <q-input class="form__inputsFilterPays" dense borderless v-model="filters.date_to" type="date" label="Hasta" />
            </div>
          </div>
          
        </section>
        <section class="pb-5 mt-5">
          <div class="flex justify-evenly mt-2">
            <q-btn label="Restablecer"  unelevated class="q-mx-sm " color="primary" outline  style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " @click="resetFilters()" />
            <q-btn label="Aplicar"  unelevated class="q-mx-sm " color="primary"   style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " :loading="loading" @click="updateList" />
          </div>
        </section>
      </div>
    </q-card>
  </q-dialog>
</template>
<style lang="scss">
.form__inputsFilterPays{
  &.q-field--auto-height.q-field--dense.q-field--labeled .q-field__control-container{
    padding-top: 10px!important;
  }
  & .q-field__label{
    transform: translateY(-85%) translateX(-10px) scale(0.75)!important;
    background: white;
    padding: 2px 15px;
  }
}
.header-sectionModal{
  height: 8%;
  overflow: hidden;
}
.content-sectionModal{
  height: 92%;
  overflow: auto;
}
.statusRadioGroup{
  & .van-radio__label {
    font-size: 1rem;
    font-weight: 500;
    color:rgb(53, 53, 53);
  }
  & .van-radio{
    justify-content: space-between;
  }
}
.filterNoticeDialog{
  margin-left: 0%;
  overflow: hidden;
  position: relative;
  & .dialog_document{
    width: 100%;
    border-radius: 0rem !important;
    height: 100%;
    overflow: hidden;

  }
  & .q-dialog__inner--minimized{
    padding: 0px;
  }
}

.form__inputsFilterPays {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
@media (max-width: 780px) {
  .form__inputsFilterPays {
    & .q-field__inner {

      padding: 0.1rem 1rem;
    }
  }

.filterNoticeDialog{
  & .dialog_document{
    max-height: 100%!important;
  }
}
}
</style>

