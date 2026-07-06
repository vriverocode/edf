<script setup>
import moment from 'moment';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { useAuthStore } from '@//services/store/auth.services';
import { storeToRefs } from 'pinia';
import anuncio from '@/assets/img/menu/anuncio.png'
import { ref, watch } from 'vue';
moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})
const emit = defineEmits(['openModal'])

const props = defineProps({
  announces: {
    type: Array,
    default: []
  },
  myPost: Boolean
})
const isMyPost = ref(props.myPost)
const { user } = storeToRefs(useAuthStore()) 
const router = useRouter()
const dataContactFormat = (dataString) => {
  let {name} = JSON.parse(dataString);
  return name
}
const showDialog = (id, modal) => {
  emit('openModal', id, modal)
}
const goTo = (id) => {
  router.push(`/client/notice/view/${id}`);
}
watch(() => props.myPost, (newValue) => {
  isMyPost.value = newValue
});

</script>
<template>
  <div v-if="announces.length > 0" class="space-y-5 md:px-5" >
    <div class="notice__item" v-for="announce in announces" :key="announce.id" >
      <div class="py-1 pl-4 pr-3 " @click="goTo(announce.id)">
        <div class="notices-badge px-3 ">
          Nuevo
        </div>
        <div class="notices-badgeStatus px-3" :class="'bg-'+announce.status_color" v-if="(announce.user_id == user.id && isMyPost) || (announce.status !== 2 && user.id == 1)">
          {{ announce.status_label }}
        </div>
        <div>
          <div class="notice__item--title">{{announce.title}}</div>
          <div class="text-grey-6 mb-1" style="font-size: 0.65rem;">
            {{ announce.group_label }} > {{ announce.category_label }}
          </div>
          <div class="notice__item--description text-stone-400 my-1">
            {{ announce.description.substring(0,94) }}...
          </div>

        </div>
      </div>
      <div class="notice__item-bottom pl-4 pr-3 py-2">
        <div class="flex items-center justify-between ">
          <div class="notice__item-bottom--postBy pt-1" @click="goTo(announce.id)">
            Publicado por: {{dataContactFormat(announce.data_contact)}}
          </div>
          <div class="notice__item-bottom--dayPost flex items-center justify-between ">
            <div class="mr-2">
              {{ moment(announce.created_at).format('DD MMM YYYY') }}
            </div>
            <div  v-if="user.id == announce.user_id">
              <q-btn size="xs" round="" color="primary" flat="">
                <div v-html="iconsApp.optionsBook" />
                <q-menu>
                  <q-list style="min-width: 150px">
                    <q-item clickable v-close-popup  @click="showDialog(announce.id, 'update')"  >
                      <q-item-section>Editar anuncio</q-item-section>
                    </q-item>
                    <q-separator />
                    <q-item clickable v-close-popup @click="showDialog(announce.id, 'delete')" >
                      <q-item-section class="text-negative">Borrar anuncio</q-item-section>
                    </q-item>
                    <q-separator />
                  </q-list>
                </q-menu>
              </q-btn>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Estado vacío -->
  <div v-else class="flex flex-col items-center justify-center py-20">
    <div class="w-24 h-24 bg-blue-100 rounded-full flex items-center justify-center mb-6">
      <img :src="anuncio  " class="md:w-auto h-3/5"/>
    </div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">Sin anuncios por el momento</h3>
    <p class="text-gray-600 text-center mb-6"> Aquí encontrarás las últimas novedades y comunicados del edificio</p>
  </div>
</template>