<script setup>
import { Notify } from 'quasar'
import { ref, watch, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';

import { RadioGroup, Radio } from 'vant';

import { useComunAreaStore } from '@/services/store/comunArea.store';
import { useAuthStore } from '@/services/store/auth.services';
import { useApartmentStore } from '@/services/store/apartment.store';

  const emit = defineEmits(['closeModal', 'updateList'])

  const props = defineProps({
    dialog: Boolean,
  })
  const router = useRouter()
  const loading = ref(false)
  const dialog  = ref(props.dialog)

  const hideModal = () => {
    emit('closeModal')
  }

  const authStore = useAuthStore()
  const apartmentStore = useApartmentStore()

  const isAdmin = computed(() => {
    return authStore.user?.rol_id === 1 || authStore.user?.rol_id === 2 || ['admin', 'super-admin'].includes(authStore.user?.rol?.name?.toLowerCase() || '')
  })

  const filters = ref({
    status: -1,
    area_id: '',
    department_id: '',
    date_from: '',
    date_to: '',
    amount_type: '',
    sort_by: 'created_at',
    sort_dir: 'desc'
  })
  const areas = ref([])
  const departments = ref([])
  const updateList = () => {
    emit('closeModal')
    emit('updateList', { ...filters.value })
  }

  watch(() => props.dialog, (newValue) => {
    dialog.value = newValue
  });

  const resetFilters = () => {
    filters.value = {
      status: -1,
      area_id: '',
      department_id: '',
      date_from: '',
      date_to: '',
      amount_type: '',
      sort_by: 'created_at',
      sort_dir: 'desc'
    }
  }

  const loadAreas = () => {
    useComunAreaStore().getAllComunAreas()
    .then((data) => {
      if (data.code !== 200) return;
      areas.value = (data.data || []).map(a => ({ label: a.name, value: a.id }))
    })
  }

  const loadDepartments = async () => {
    if (isAdmin.value) {
      try {
        const response = await apartmentStore.getApartmentsByFind('')
        if (response && Array.isArray(response)) {
          departments.value = response
        } else if (response && response.data) {
          departments.value = response.data
        }
      } catch (e) {
        console.error(e)
      }
    }
  }

  onMounted(() => {
    loadAreas();
    loadDepartments();
  })

</script>
<template>
  <q-dialog v-model="dialog" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
      <q-card class="dialog_document public ">
        <div class=" header-sectionModal" style="border-bottom: 1px solid lightgray;">
          <div class="flex justify-between items-center pr-5 pl-2 py-2" >
            <q-btn round outline icon="eva-arrow-back-outline" color="primary"  @click="resetFilters(), hideModal() "/>
            <div class="text-2xl text-primary font-bold pt-1">
              Filtrar
            </div>
          </div>
        </div>
        <div class="content-sectionModal">
          <section class="content__modalSectionRifa md:mt-0 mt-0  py-2">
            
            <div class="row pt-3 pb-2 px-5">
              <div class="mb-1 text-lg font-medium text-primary">
                Estado de pago
              </div>
              <div class="col-12">
                <radio-group v-model="filters.status" class="row statusRadioGroup">
                  <div class="col-12 my-2" >
                    <radio :name="0" icon-size="1.3rem" label-position="left">Canceladas</radio>
                  </div>
                  <div class="col-12 my-2" >
                    <radio :name="1" icon-size="1.3rem" label-position="left">Pendientes</radio>
                  </div>
                  <div class="col-12 my-2" >
                    <radio :name="2" icon-size="1.3rem" label-position="left">Pagos Pendientes</radio>
  
                  </div>
                  <div class="col-12 my-2" >
                    <radio :name="3" icon-size="1.3rem" label-position="left">Exitosas</radio>
                  </div>
                  <div class="col-12 my-2" >
                    <radio :name="4" icon-size="1.3rem" label-position="left">Completadas</radio>
                  </div>
                  <div class="col-12 my-2" >
                    <radio :name="7" icon-size="1.3rem" label-position="left">Todas</radio>
                  </div>
                </radio-group>
              </div>
              
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
              <div class="mb-3 text-lg font-medium text-primary col-12">
                Area reservada
              </div>
              <div class="col-12 ">
                <q-select 
                class="form__inputsFilterBookings"
                v-model="filters.area_id"
                :options="areas"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                use-input input-debounce="200"
                label="Selecciona un área"
                dense borderless />
              </div>
              
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;" v-if="isAdmin">
              <div class="mb-3 text-lg font-medium text-primary col-12">
                Departamento
              </div>
              <div class="col-12 ">
                <q-select 
                class="form__inputsFilterBookings"
                v-model="filters.department_id"
                :options="departments"
                option-label="number"
                option-value="id"
                emit-value
                map-options
                use-input input-debounce="200"
                label="Selecciona un departamento"
                dense borderless />
              </div>
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
              <div class="mb-4 text-lg font-medium text-primary col-12">
                Rango de fechas de reservación
              </div>
              <div class="col-6 pr-1">
                <q-input class="form__inputsFilterBookings" dense borderless v-model="filters.date_from" type="date" label="Desde" />
              </div>
              <div class="col-6 pl-1">
                <q-input class="form__inputsFilterBookings" dense borderless v-model="filters.date_to" type="date" label="Hasta" />
              </div>
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
              <div class="mb-4 text-lg font-medium text-primary col-12">
                Tipo de monto
              </div>
              <div class="col-12 ">
                <q-select 
                  class="form__inputsFilterBookings"
                  v-model="filters.amount_type"
                  :options="[{label:'Todos',value:''},{label:'Gratis',value:'free'},{label:'Con pago',value:'paid'}]"
                  option-label="label"
                  option-value="value"
                  emit-value
                  map-options
                  label="Monto"
                  dense borderless />
              </div>
            </div>
            <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
              <div class="mb-4 text-lg font-medium text-primary col-12">
                Ordenar por
              </div>
              <div class="col-6 pr-1">
                <q-select 
                  class="form__inputsFilterBookings"
                  v-model="filters.sort_by"
                  :options="[
                    {label:'Creación', value:'created_at'},
                    {label:'Fecha reserva', value:'date'},
                    {label:'Estado', value:'status'},
                    {label:'Monto', value:'amount'}
                  ]"
                  option-label="label"
                  option-value="value"
                  emit-value
                  map-options
                  dense borderless />
              </div>
              <div class="col-6 pl-1">
                <q-select 
                  class="form__inputsFilterBookings"
                  v-model="filters.sort_dir"
                  :options="[{label:'Descendente',value:'desc'},{label:'Ascendente',value:'asc'}]"
                  option-label="label"
                  option-value="value"
                  emit-value
                  map-options
                  dense borderless />
              </div>
            </div>
          </section>
          <section class="pb-5">
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
.filterDialog{
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

.form__inputsFilterBookings {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
@media (max-width: 780px) {
  .form__inputsFilterBookings {
    & .q-field__inner {

      padding: 0.1rem 1rem;
    }
  }
}

@media (max-width: 780px) {
.filterDialog{
  & .dialog_document{
    max-height: 100%!important;
  }
}
}
</style>