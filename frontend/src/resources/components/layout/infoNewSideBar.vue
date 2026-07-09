<script setup>
import {  inject, onMounted, ref} from 'vue';

const emitter = inject('emitter')

const showXs = ref( window.screen.width < 780 ? false : true)

const showMovil = () => {
  window.screen.width < 780 
  ? showXs.value = !showXs.value
  : ''

}


onMounted(() =>{
  emitter.on('showInfoNews', showMovil) 
})

</script>
<template>
  <!-- <aside class=" aside__menuClient q-px-md blockx md:hiddenx" :class="showXs ? 'blockW' : 'hiddenW'"> -->
  <aside class=" aside__menuClient q-px-md  hidden" >

    <div>
      <div style="position: absolute;left: -1.5rem; top: 0rem;" class=" md:hidden">
        <q-btn rounded unelevated icon="eva-close-outline" class="close_buttonSidebar q-py-md" size="md"  text-color="white"  @click="showXs = false"/>
      </div>
      <div class="text-center text-bold mt-8 text-2xl text-primary">
        <!-- <img :src="logo" alt=""  style="width:12rem" class="mx-auto"> -->
        Notificaciones
      </div>
      
    </div>
  </aside>
</template>
<style lang="scss">
.hiddenW{
  width: 5rem;
  transform: translateX(60rem);
}
.blockW{
  width:90%;
}
.aside__menuClient{
  box-shadow: 5px 0px 26px 0px #1e1e1e;
  background: #e7e7e7;
  position: absolute;
  z-index: 110;
  right: 0;
  top:0;
  bottom: 0;
  transition: all 0.8s ease;
}
.close_buttonSidebar{
    background: #a2a2a2!important;



}
.item__menu-aside{
  
  background: #f9fafb;
  border-radius: 0.8rem;
  cursor: pointer;
  transition: all  ease 0.5s;
  color: black;
  &:hover{
    background: #414141;
    & .text-center {
      color: white
    }
  }
  &:has(.active_link){
    background: #414141;
    & .text-center {
      color: white
    }

  }

}
@media (max-width: 768px){

}

</style>