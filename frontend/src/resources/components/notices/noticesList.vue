<script setup>
import moment from 'moment';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { useAuthStore } from '@//services/store/auth.services';
import { storeToRefs } from 'pinia';
import anuncio from '@/assets/img/menu/anuncio.png'
moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})
const emit = defineEmits(['openModal'])

const props = defineProps({
  notices: {
    type: Array,
    default: []
  },
})
const { user } = storeToRefs(useAuthStore()) 
const router = useRouter()
const showDialog = (id, modal) => {
  emit('openModal', id, modal)
}
const goTo = (id) => {
  router.push(`/client/notice/view/${id}`);
}

const segmentLabel = (notice) => {
  if (!notice.segment_type || notice.segment_type === 'all') return ''
  const labels = { tower: 'Torre', floor: 'Piso', department: 'Depto', user: 'Usuario' }
  const label = labels[notice.segment_type] || notice.segment_type
  const ids = Array.isArray(notice.segment_ids) ? notice.segment_ids : []
  return `${label}: ${ids.join(', ')}`
}

</script>
<template>
  <div v-if="notices.length > 0" class="space-y-5 md:px-5" style="cursor:pointer">
    <div class="notice__item" v-for="notice in notices" :key="notice.id" >
      <div class="py-1 pl-4 pr-3" @click="goTo(notice.id)">
        <div class="notices-badge px-3 ">
          Nuevo
        </div>
        <div>
          <div class="notice__item--title">{{notice.title}}</div>
          <div v-if="segmentLabel(notice)" class="text-caption text-primary q-my-xs">
            {{ segmentLabel(notice) }}
          </div>
          <div class="notice__item--description text-stone-400 my-1 whitespace-pre-line">
            {{ notice.description.substring(0,94) }}...
          </div>

        </div>
      </div>
      <div class="notice__item-bottom pl-4 flex items-center justify-between pr-3 py-2">
        <div class="notice__item-bottom--postBy" @click="goTo(notice.id)">
          Publicado por: {{'Admin'}}
        </div>
        <div class="notice__item-bottom--dayPost">
          {{ moment(notice.created_at).format('DD MMM YYYY') }}
        </div>
        <div  v-if="user.id == notice.user_id">
          <q-btn size="xs" round="" color="primary" flat="">
            <div v-html="iconsApp.optionsBook" />
            <q-menu>
              <q-list style="min-width: 150px">
                <q-item clickable v-close-popup  @click="showDialog(notice.id, 'update')"  >
                  <q-item-section>Editar noticia</q-item-section>
                </q-item>
                <q-separator />
                <q-item clickable v-close-popup @click="showDialog(notice.id, 'delete')" >
                  <q-item-section class="text-negative">Borrar noticia</q-item-section>
                </q-item>
                <q-separator />
              </q-list>
            </q-menu>
          </q-btn>
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