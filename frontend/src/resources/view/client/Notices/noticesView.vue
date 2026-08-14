<script setup>
import { useNoticeStore } from '@/services/store/notice.store';
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import iconsApp from '@/assets/icons/index'
import { useAuthStore } from '@//services/store/auth.services';
import { storeToRefs } from 'pinia';
import { ZoomImg } from "vue3-zoomer";
import { Notify } from 'quasar';

const { user } = storeToRefs(useAuthStore())
const ready = ref(false)
const notice = ref({})
const route = useRoute()
const router = useRouter()
const attachImages = ref([])
const noticesViews = ref([])
const loading = ref(false)
const getNoticesById = () => {
  ready.value = false
  useNoticeStore().getNoticeById(route.params.id)
  .then((data) => {
    Object.assign(notice.value, data.data);
    setAttachImages(notice.value.img)
    noticesViews.value = JSON.parse(notice.value.views)
  })
  .catch((response) => {
    console.error(response)
  })
  .finally(() => {
    ready.value = true
  })
}
const setAttachImages = (source) => {
  let images = JSON.parse(source)
  attachImages.value = images;
}
const dataContactFormat = (dataString) => {
  let {name, apartment} = JSON.parse(dataString);
  return `${name} - ${apartment}`
}
const setInViewUser = () => {
  useNoticeStore().setViewer(route.params.id)
}
const setStatusAnnounce = (status) => {
  loading.value = true
  const data = {
    id: notice.value.id,
    status
  }
  useNoticeStore().setNewStatus(data)
  .then((response) => {
    setMessageNotifyByStatus(response.data.status)
    setTimeout(() => {
      router.go(-1)
    }, 1000);
  })
  .catch((data) => {
    console.error(data)
  })
  .finally(() => loading.value = false)
}
const setMessageNotifyByStatus = (status) => {
  const type = status == 2 ? 'positive' : 'negative'
  const message = status == 2 
  ? 'Publicación aprobada con exito'
  : 'Publicación rechazada'

  showDialog(type, message)
}
const showDialog = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
onMounted(() => {
  setInViewUser()
  getNoticesById()
})
</script>
<template>
  <section class="h-full" style="overflow: hidden;">
    <div v-if="ready" class="h-full pb-8 md:px-32" style="overflow: scroll; ">
      <div v-if="Object.values(notice).length > 0" class="px-4">
        <div class="mt-4 flex items-center justify-between relative position-relative" >
          <div class=" text-h6 text-primary font-bold">
            ► {{ notice.title }}
          </div>
          <div class="notices-badgeStatusRelative px-3 ml-4" :class="'bg-'+notice.status_color" v-if="notice.user_id == user.id ||  user.rol_id == 1 ">
            {{ notice.status_label }}
          </div>
        </div>
        <div class="mt-4 text-sm text-stone-600 whitespace-pre-line" style="line-height: 1.5;">
          {{ notice.description }}
        </div>
        <div v-if="attachImages?.length > 0" class="row md:mt-5">
          <div class="col-12 mt-5 md:mt-0 col-md-6 mx-auto" v-for="(image, index) in attachImages" :key="index">
            <ZoomImg :src="image" alt="post_image" />
          </div>
        </div>
        <div class="flex items-center mt-3 " v-if="notice.user_id == user.id">
          <div class="mr-1 text-xs text-grey-7">Visto por: {{ noticesViews.length }}</div>
          <q-icon name="eva-eye-outline" size="xs" color="grey-7"/> 
        </div>
        <div class="mt-5 text-xs text-stone-400" style="line-height: 1.5;">
          Publicado por: {{ notice.type == 1 ? 'Administrador' : dataContactFormat(notice.data_contact) }}
        </div>
        <div v-if="user.rol_id == 1 && (notice.status == 1)" class="mt-4 w-full justify-between flex items-center">
          <q-btn label="Rechazar" unelevated class="q-mx-sm " color="negative" outline
              style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;" @click="setStatusAnnounce(0)" :loading="loading"  />
              
          <q-btn label="Aprobar" type="submit" unelevated class="q-mx-sm " color="primary" :loading="loading" 
          style="border-radius: 0.8rem; padding:0px  2rem!important; font-size: 1rem;  " @click="setStatusAnnounce(2)"/>
        </div>
      </div>
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <div v-html="iconsApp.megaphone" />
        </div>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Error al intentar obtener noticia</h3>
        <p class="text-primary text-center mb-6" style="text-decoration: underline;" @click="getNoticesById()">Reintentar.</p>
      </div>
    </div>
    <div v-else class="flex justify-center column items-center py-20">
      <q-spinner-comment color="primary" size="5rem" />
      <div class="text-stone-500 text-center mx-12">Cargando información</div>
    </div>
  </section>
</template>

<style lang="scss">
.notices-badgeStatusRelative{
  position: relative;
  color: white;
  font-size: 0.7rem;
  border-radius: 7px;

}
</style>