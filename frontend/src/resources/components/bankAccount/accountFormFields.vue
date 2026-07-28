<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const form = computed({
  get: () => ({
    name: props.modelValue.name || '',
    data: {
      type: props.modelValue.data?.type || 'bank',
      entity: props.modelValue.data?.entity || '',
      account_number: props.modelValue.data?.account_number || '',
      cci: props.modelValue.data?.cci || '',
      holder_name: props.modelValue.data?.holder_name || '',
      yape_phone: props.modelValue.data?.yape_phone || '',
      yape_name: props.modelValue.data?.yape_name || '',
    },
  }),
  set: (val) => emit('update:modelValue', val),
})

const typeOptions = [
  { label: 'Cuenta de banco', value: 'bank' },
  { label: 'Yape', value: 'yape' },
]
</script>

<template>
  <div class="col-md-6 col-12 my-1 px-2 md:px-12">
    <div class="text-subtitle2 text-bold text-black">
      Tipo de cuenta
    </div>
    <q-select
      :model-value="form.data.type"
      :options="typeOptions"
      option-value="value"
      option-label="label"
      emit-value
      map-options
      borderless
      dense
      class="form__inputsCR mt-2"
      behavior="menu"
      lazy-rules
      :rules="[v => !!v || 'Selecciona un tipo de cuenta']"
      @update:model-value="form = { ...form, data: { ...form.data, type: $event } }"
    />
  </div>

  <div class="col-md-6 col-12 my-1 px-2 md:px-12">
    <div class="text-subtitle2 text-bold text-black">
      Nombre de la cuenta
    </div>
    <q-input
      :model-value="form.name"
      borderless
      dense
      placeholder="Ej. Mi cuenta BCP"
      clearable
      class="form__inputsCR mt-2"
      color="primary"
      lazy-rules
      :rules="[v => !!v || 'El nombre es obligatorio']"
      @update:model-value="form = { ...form, name: $event }"
    />
  </div>

  <template v-if="form.data.type === 'bank'">
    <div class="col-md-6 col-12 my-1 px-2 md:px-12">
      <div class="text-subtitle2 text-bold text-black">
        Banco
      </div>
      <q-input
        :model-value="form.data.entity"
        borderless
        dense
        placeholder="Ej. BCP, Interbank, BBVA..."
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        lazy-rules
        :rules="[v => !!v || 'Banco es obligatorio']"
        @update:model-value="form = { ...form, data: { ...form.data, entity: $event } }"
      />
    </div>

    <div class="col-md-6 col-12 my-1 px-2 md:px-12">
      <div class="text-subtitle2 text-bold text-black">
        Número de cuenta
      </div>
      <q-input
        :model-value="form.data.account_number"
        borderless
        dense
        placeholder="Número de cuenta"
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        lazy-rules
        :rules="[v => !!v || 'Número de cuenta es obligatorio']"
        @update:model-value="form = { ...form, data: { ...form.data, account_number: $event } }"
      />
    </div>

    <div class="col-md-6 col-12 my-1 px-2 md:px-12">
      <div class="text-subtitle2 text-bold text-black">
        CCI
      </div>
      <q-input
        :model-value="form.data.cci"
        borderless
        dense
        placeholder="Código de cuenta interbancario"
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        @update:model-value="form = { ...form, data: { ...form.data, cci: $event } }"
      />
    </div>

    <div class="col-md-6 col-12 my-1 px-2 md:px-12 pt-4 md:pt-0">
      <div class="text-subtitle2 text-bold text-black">
        Titular
      </div>
      <q-input
        :model-value="form.data.holder_name"
        borderless
        dense
        placeholder="Nombre del titular de la cuenta"
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        lazy-rules
        :rules="[v => !!v || 'Titular es obligatorio']"
        @update:model-value="form = { ...form, data: { ...form.data, holder_name: $event } }"
      />
    </div>
  </template>

  <template v-else>
    <div class="col-md-6 col-12 my-1 px-2 md:px-12">
      <div class="text-subtitle2 text-bold text-black">
        Teléfono Yape
      </div>
      <q-input
        :model-value="form.data.yape_phone"
        borderless
        dense
        placeholder="Número de teléfono asociado a Yape"
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        lazy-rules
        :rules="[v => !!v || 'Teléfono es obligatorio']"
        @update:model-value="form = { ...form, data: { ...form.data, yape_phone: $event } }"
      />
    </div>

    <div class="col-md-6 col-12 my-1 px-2 md:px-12">
      <div class="text-subtitle2 text-bold text-black">
        Nombre en Yape
      </div>
      <q-input
        :model-value="form.data.yape_name"
        borderless
        dense
        placeholder="Nombre que aparece en Yape"
        clearable
        class="form__inputsCR mt-2"
        color="primary"
        lazy-rules
        :rules="[v => !!v || 'Nombre es obligatorio']"
        @update:model-value="form = { ...form, data: { ...form.data, yape_name: $event } }"
      />
    </div>
  </template>
</template>

<style lang="scss">
.form__inputsCR {
    & .q-field__inner {
        box-shadow: 0px 3px 5px 0px #bfbfbfa3;
        border-radius: 0.8rem;
        border: 1px solid rgb(223, 223, 223);
        padding: 0px 2rem;
    }
}

@media (max-width: 780px) {
    .form__inputsCR {
        & .q-field__inner {
            padding: 0px 1rem;
        }
    }
}
</style>
