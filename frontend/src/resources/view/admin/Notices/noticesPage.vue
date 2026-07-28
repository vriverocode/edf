<script setup>
import { ref, onMounted } from 'vue';
import { useNoticeStore } from '@/services/store/notice.store';
import NoticesList from '@/components/notices/noticesList.vue';
import AnnouncesList from '@/components/notices/announcesList.vue';
import deleteAnnounceModal from '@/components/notices/deleteAnnounceModal.vue';
import updateAnnounceModal from '@/components/notices/updateAnnounceModal.vue';
import filterAnnouncesList from '@/components/notices/filterAnnouncesList.vue';
import createNoticeModal from '@/components/notices/createNoticeModal.vue';
import updateNoticeModal from '@/components/notices/updateNoticeModal.vue';
import deleteNoticeModal from '@/components/notices/deleteNoticeModal.vue';


const notices = ref([]);
const announces = ref([]);
const loading = ref(true);
const noticeStore = useNoticeStore();
const panelToShow = ref('notices')
const modal = ref('')
const floatTab = ref(false);
const activeFilterSearch = ref('active')
const filters = ref({
  status: 4,
  group: '',
  category: '',
  post_by: '',
  date_from: '',
  date_to: '',
});
const selectedNotice = ref({})
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

const getSelectedNotice = (id, modal) => {
  selectedNotice.value = notices.value.find((notice) => notice.id == id)
  showModal(modal)
}
const closeModal = () => {
  modal.value = ''
}
const isUsingFilter = () => {
  const filtersToValidate = {...filters.value}
  const { status } = filtersToValidate
  let result = ''


  if( status != 4) {
    result = 'active-filter'
  }
  Object.values(filtersToValidate).forEach((filter, index) => {
    if(index != 0){
      if(filter !== ''){
        result = 'active-filter'
      }
    }
  });
  activeFilterSearch.value = result
}
const getNoticesWithFilter = (newFilter) => {
  filters.value = { ...filters.value, ...newFilter };
  isUsingFilter()
  getNotices();
}

const resetFilter = () => {
  filters.value = {
    status: 4,
    group: '',
    category: '',
    post_by: '',
    date_from: '',
    date_to: '',
  }
  isUsingFilter()

}
const setPanelShow = (value) => {
  panelToShow.value = value
  resetFilter()
}
onMounted(() => {
  getNotices();
});
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <!-- Lista de pagos -->
    <div class="h-full" style="overflow: auto; position: relative;">
      <div class="flex justify-center  mt-3 bg-stone-200 py-2 buttonsContainer" >
        <div>
          <div class="buttonSwichtNotices  px-6 mx-3" :class="{'active':panelToShow == 'notices'}" @click="setPanelShow('notices')">
            Noticias
          </div>
        </div>
        <div>
          <div class="buttonSwichtNotices  px-6 mx-3" :class="{'active':panelToShow == 'announces'}" @click="setPanelShow('announces')">
            Anuncios
          </div>
        </div>
      </div>
      <div class="flex justify-end md:pr-5 px-4  mt-2 ">
        <q-btn
          outline
          color="primary"
          :class="activeFilterSearch"
          icon="eva-funnel-outline"
          @click="modal = 'filter'"
          v-if="panelToShow=='announces'"
        />
      </div>
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Lista de pagos -->
        <noticesList v-if="panelToShow == 'notices'" :notices="notices" @openModal="getSelectedNotice" />
        <announcesList v-if="panelToShow == 'announces'" 
         :announces="announces" 
         :myPost="(filters.only_my_posts =='active')"
        />
      </div>
      <div class="createAnnouncesFloat" v-if="panelToShow == 'notices'"  >
        <q-fab
          v-model="floatTab"
          color="primary"
          size="lg"
          icon="eva-plus-outline"
          direction="up"
          v-if="panelToShow == 'notices'"
        >
          <q-fab-action class="noticeOption" label-position="right" color="primary" icon="eva-plus-outline" label="Crear noticia" @click="showModal('create_notice')"/>
        </q-fab>
      </div>

      <createNoticeModal :dialog="(modal=='create_notice')" @closeModal="closeModal" @updateList="getNotices()" />
      <filterAnnouncesList 
        :dialog="(modal == 'filter')" 
        :typeSearch="panelToShow"
        @closeModal="closeModal"
        @updateList="getNoticesWithFilter"
      />
      <template v-if="Object.values(selectedNotice).length > 0 ">
        <deleteNoticeModal :dialog="(modal=='delete')" :notice="selectedNotice"  @closeModal="closeModal" @updateList="getNotices()" />
        <updateNoticeModal :dialog="(modal=='update')" :notice="selectedNotice"  @closeModal="closeModal" @updateList="getNotices()" />
      </template>
      
    </div>
  </div>
</template>

<style  lang="scss">
.q-btn.active-filter{
  background: $primary !important;
  color: white !important;
}
.createAnnouncesFloat{
  & .q-fab__actions {
    align-items: end;
  }
}
.noticeOption{
  &.q-btn{
    transform: translateX(10px) translateY(0px)!important;
  }
}
.createAnnouncesFloat{
  position: fixed;
  bottom: 3rem;
  right: 1rem;
}
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

