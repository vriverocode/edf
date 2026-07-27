<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:modelValue'])

const form = computed({
  get: () => ({
    type: props.modelValue.type || 'bank',
    entity: props.modelValue.entity || '',
    account_number: props.modelValue.account_number || '',
    cci: props.modelValue.cci || '',
    holder_name: props.modelValue.holder_name || '',
    yape_phone: props.modelValue.yape_phone || '',
    yape_name: props.modelValue.yape_name || '',
  }),
  set: (val) => emit('update:modelValue', val),
})

const typeOptions = [
  { label: 'Cuenta de banco', value: 'bank' },
  { label: 'Yape', value: 'yape' },
]

const setType = (type) => {
  form.value = { ...form.value, type }
}
</script>

<template>
  <div class="q-gutter-y-md">
    <div class="text-subtitle2 text-black">Tipo de cuenta</div>
    <q-option-group
      :model-value="form.type"
      :options="typeOptions"
      color="primary"
      inline
      @update:model-value="setType"
    />

    <template v-if="form.type === 'bank'">
      <div class="text-subtitle2 text-black">Banco *</div>
      <q-input
        dense outlined
        :model-value="form.entity"
        placeholder="Ej. BCP, Interbank, BBVA..."
        :rules="[v => !!v || 'Banco es obligatorio']"
        @update:model-value="form = { ...form, entity: $event }"
      />

      <div class="text-subtitle2 text-black">Número de cuenta *</div>
      <q-input
        dense outlined
        :model-value="form.account_number"
        placeholder="Número de cuenta"
        :rules="[v => !!v || 'Número de cuenta es obligatorio']"
        @update:model-value="form = { ...form, account_number: $event }"
      />

      <div class="text-subtitle2 text-black">CCI</div>
      <q-input
        dense outlined
        :model-value="form.cci"
        placeholder="Código de cuenta interbancario"
        @update:model-value="form = { ...form, cci: $event }"
      />

      <div class="text-subtitle2 text-black">Titular *</div>
      <q-input
        dense outlined
        :model-value="form.holder_name"
        placeholder="Nombre del titular de la cuenta"
        :rules="[v => !!v || 'Titular es obligatorio']"
        @update:model-value="form = { ...form, holder_name: $event }"
      />
    </template>

    <template v-else>
      <div class="text-subtitle2 text-black">Teléfono Yape *</div>
      <q-input
        dense outlined
        :model-value="form.yape_phone"
        placeholder="Número de teléfono asociado a Yape"
        :rules="[v => !!v || 'Teléfono es obligatorio']"
        @update:model-value="form = { ...form, yape_phone: $event }"
      />

      <div class="text-subtitle2 text-black">Nombre en Yape *</div>
      <q-input
        dense outlined
        :model-value="form.yape_name"
        placeholder="Nombre que aparece en Yape"
        :rules="[v => !!v || 'Nombre es obligatorio']"
        @update:model-value="form = { ...form, yape_name: $event }"
      />
    </template>
  </div>
</template>
