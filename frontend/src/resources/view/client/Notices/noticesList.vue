<script setup>
import { ref, onMounted } from 'vue';
import { useNoticeStore } from '@/services/store/notice.store';
import { useRouter } from 'vue-router';
import NoticesList from '@/components/notices/noticesList.vue';
import AnnouncesList from '@/components/notices/announcesList.vue';
import createAnnouncesModal from '@/components/notices/createAnnouncesModal.vue';
import deleteAnnounceModal from '@/components/notices/deleteAnnounceModal.vue';
import updateAnnounceModal from '@/components/notices/updateAnnounceModal.vue';
import filterAnnouncesList from '@//components/notices/filterAnnouncesList.vue';

const notices = ref([]);
const announces = ref([]);
const loading = ref(true);
const noticeStore = useNoticeStore();
const panelToShow = ref('notices')
const modal = ref('')
const filters = ref({
  status: 2,
  group: '',
  category: '',
  post_by:'',
  date_from: '',
  date_to: '',
  only_my_posts:''
});
const selectedAnnounce = ref({})
const showModal = (modalId) => {
  modal.value = modalId
}

const getNotices = () => {
  loading.value = true;
  noticeStore.getNotices(filters.value)
    .then((response) => {
      if (response.code !== 200) throw response;
      notices.value = response.data.notices;
      announces.value = response.data.announces;

    })
    .catch((response) => {
      console.error(response);
    })
    .finally(() => {
      loading.value = false;
    });
}

const getSelectedAnnounce = (id, modal) => {
  selectedAnnounce.value = announces.value.find((announce) => announce.id == id)
  showModal(modal)
}
const closeModal = () => {
  modal.value = ''
}
const getNoticesWithFilter = (newFilter) =>{
  filters.value = { ...filters.value, ...newFilter };
  getNotices();
}

onMounted(() => {
  getNotices();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <!-- Lista de pagos -->
    <div class="h-full" style="overflow: auto; position: relative;">
      <div class="flex justify-center mt-3 bg-stone-200 py-2 buttonsContainer" >
        <div>
          <div class="buttonSwichtNotices  px-6 mx-3" :class="{'active':panelToShow === 'notices'}" @click="panelToShow ='notices'">
            Noticias
          </div>
        </div>
        <div>
          <div class="buttonSwichtNotices  px-6 mx-3" :class="{'active':panelToShow === 'announces'}" @click="panelToShow ='announces'">
            {{ filters.only_my_posts == 'active' ?  'Mis anuncios' : 'Anuncios'}}
          </div>
        </div>
      </div>
      <div class="flex justify-end w-full md:pr-5 px-4 mt-2">
        <q-btn outline color="primary" icon="eva-funnel-outline" @click="modal = 'filter'" v-if="panelToShow == 'announces'" />
      </div>
      <div class="flex items-center w-full gap-2 px-4 md:px-28 pt-2" v-if="panelToShow == 'announces'">
        <q-btn color="primary" unelevated class="flex-1" style="border-radius: 0.5rem;" @click="showModal('create_announce')">
          <div class="flex items-center py-2">
            <q-icon name="eva-plus-outline" />
            <div class="q-pt-xs text-bold pl-1">Crear anuncio</div>
          </div>
        </q-btn>
      </div>
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de pagos -->
        <noticesList v-if="panelToShow == 'notices'" :notices="notices" />
        <announcesList v-if="panelToShow == 'announces'" 
         :announces="announces" 
         :myPost="(filters.only_my_posts =='active')"
         @openModal="getSelectedAnnounce"
        />
      </div>
      <createAnnouncesModal :dialog="(modal=='create_announce')" @closeModal="closeModal" @updateList="getNotices()"/>
      <filterAnnouncesList 
        :dialog="(modal == 'filter')" 
        :typeSearch="panelToShow"
        :isOnlyMyPost="filters.only_my_posts"
        @closeModal="closeModal"
        @updateList="getNoticesWithFilter"
      />
      <template v-if="Object.values(selectedAnnounce).length > 0 ">
        <deleteAnnounceModal :dialog="(modal=='delete')" :announce="selectedAnnounce"  @closeModal="closeModal" @updateList="getNotices()" />
        <updateAnnounceModal :dialog="(modal=='update')" :announce="selectedAnnounce"  @closeModal="closeModal" @updateList="getNotices()" />
      </template>
    </div>


  </div>
</template>

<style  lang="scss">
.buttonsContainer{
  border-radius: 15px; 
  width: max-content; 
  margin-left: auto; margin-right: auto;
}
.buttonSwichtNotices{
  padding-top:0.39rem ;
  padding-bottom:0.39rem ;
  color: white;
  background: $primary;
  border-radius: 15px;
  opacity: 0.5;
  transition: all ease-in 0.54s;
  &:hover{
    opacity: 0.9;
  }
  &.active{
    opacity: 1;
  }
}
.notices-badge{
  position: absolute;
  background: $primary;
  color: white;
  font-size: 0.7rem;
  line-height: 1.7;
  top: 0;
  right: 0;
  border-bottom-left-radius: 7px;
}
.notices-badgeStatus{
  position: absolute;
  color: white;
  font-size: 0.7rem;
  line-height: 1.7;
  top: 0;
  right: 3.8rem;
  border-bottom-left-radius: 7px;
  border-bottom-right-radius: 7px;

}
.notice__item{
  background: white;
  border-radius: 7px;
  position: relative;
  height: max-content;
  overflow: hidden;
  box-shadow: 0px 1px 10px 1px #77777754;
  &::before{
    content: '';
    height: 100%;
    background: $primary;
    width: 6px;
    position: absolute;
  }
  &--title{
    font-size:0.95rem; font-weight: 500;
  }
  &--description{
    font-size:0.7rem; 
    font-weight: 400;
  }
  &-bottom{
    border-top: 1px dashed darkgray ;
  }
  &-bottom--postBy{
    font-size:0.75rem; 
    font-weight: 500;
  }
  &-bottom--dayPost{
    font-size:0.75rem; 
    font-weight: 500;
    color: #777;
  }
}
/* Estilos adicionales si es necesario */
</style>

