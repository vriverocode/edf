<script setup>
import { ref, watch } from 'vue';
import { RadioGroup, Radio } from 'vant';

  const emit = defineEmits(['closeModal', 'updateList'])

  const props = defineProps({
    dialog: Boolean,
  })
  const loading = ref(false)
  const dialog  = ref(props.dialog)

  const hideModal = () => {
    emit('closeModal')
  }
  const filters = ref({
    status: 4,
    pay_method: '',
    type: '',
    date_from: '',
    date_to: '',
    sort_by: 'created_at',
    sort_dir: 'desc'
  })

  const updateList = () => {
    emit('closeModal')
    emit('updateList', { ...filters.value })
  }

  watch(() => props.dialog, (newValue) => {
    dialog.value = newValue
  });

  const resetFilters = () => {
    filters.value = {
      status: 4,
      pay_method: '',
      type: '',
      date_from: '',
      date_to: '',
      sort_by: 'created_at',
      sort_dir: 'desc'
    }
  }

</script>
<template>
  <q-dialog v-model="dialog" class="filterDialog" persistent backdrop-filter="blur(0.5px)">
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
        <section class="content__modalSectionRifa md:mt-0 mt-0  py-2">
          
          <div class="row pt-3 pb-2 px-5">
            <div class="mb-1 text-lg font-medium text-primary">
              Estado de pago
            </div>
            <div class="col-12">
              <radio-group v-model="filters.status" class="row statusRadioGroup">
                <div class="col-12 my-2" >
                  <radio :name="0" icon-size="1.3rem" label-position="left">Cancelado</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="1" icon-size="1.3rem" label-position="left">Pendiente</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="2" icon-size="1.3rem" label-position="left">Exitoso</radio>
                </div>
                <div class="col-12 my-2" >
                  <radio :name="4" icon-size="1.3rem" label-position="left">Todas</radio>
                </div>
              </radio-group>
            </div>
            
          </div>
          <div class="row py-4 px-5 pb-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Método de pago
            </div>
            <div class="col-12 ">
              <q-select 
                class="form__inputsFilterPays"
                v-model="filters.pay_method"
                :options="[
                  {label:'Todos',value:''},
                  {label:'Transferencia bancaria',value:1},
                  {label:'Yape',value:2},
                  {label:'Pago en efectivo',value:3}
                ]"
                option-label="label"
                option-value="value"
                emit-value
                map-options
                label="Selecciona método"
                dense borderless />
            </div>
            
          </div>
          <div class="row py-4 px-5 pb-5" style="border-top: 1px solid lightgray;">
            <div class="mb-3 text-lg font-medium text-primary col-12">
              Tipo de pago
            </div>
            <div class="col-12 ">
              <q-select 
              class="form__inputsFilterPays"
              v-model="filters.type"
              :options="[
                {label:'Todos',value:''},
                {label:'Pago de cuota',value:1},
                {label:'Pago de reserva',value:2}
              ]"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              label="Selecciona tipo"
              dense borderless />
            </div>
            
          </div>
          <div class="row py-4 px-5 pb-5" style="border-top: 1px solid lightgray;">
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
          <div class="row py-4 px-5" style="border-top: 1px solid lightgray;">
            <div class="mb-4 text-lg font-medium text-primary col-12">
              Ordenar por
            </div>
            <div class="col-6 pr-1">
              <q-select 
                class="form__inputsFilterPays"
                v-model="filters.sort_by"
                :options="[
                  {label:'Creación', value:'created_at'},
                  {label:'Fecha de pago', value:'pay_date'},
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
                class="form__inputsFilterPays"
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

.filterDialog{
  & .dialog_document{
    max-height: 100%!important;
  }
}
}
</style>

