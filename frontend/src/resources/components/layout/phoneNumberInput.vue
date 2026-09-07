<script setup>
import { ref, watch, onMounted } from 'vue'
const emit = defineEmits(['update:modelValue'])

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  label: {
    type: String,
    default: 'Teléfono'
  },
  placeholder: {
    type: String,
    default: '123-4567'
  },
  rules: {
    type: Array,
    default: () => []
  }
})
const selectedPrefix = ref({ label: 'PE (+51)', value: '+51', flag: '🇵🇪' }) // Prefijo por defecto (Venezuela)
const phoneNumber = ref('')

const prefixes = [
  { label: 'VE (+58)', value: '+58', flag: '🇻🇪' },
  { label: 'US (+1)', value: '+1', flag: '🇺🇸' },
  { label: 'ES (+34)', value: '+34', flag: '🇪🇸' },
  { label: 'CO (+57)', value: '+57', flag: '🇨🇴' },
  { label: 'PE (+51)', value: '+51', flag: '🇵🇪' },
  // Agrega más países según necesites
]

const filteredPrefixes = ref(prefixes)

const filterFn = (val, update) => {
  if (val === '') {
    update(() => {
      filteredPrefixes.value = prefixes
    })
    return
  }

  update(() => {
    const needle = val.toLowerCase()
    filteredPrefixes.value = prefixes.filter(
      v => v.label.toLowerCase().indexOf(needle) > -1
    )
  })
}

watch([selectedPrefix, phoneNumber], ([newPrefix, newNumber]) => {
  emit('update:modelValue', `${newPrefix.value}${newNumber}`)
})

// Si necesitas inicializar el valor desde el padre
onMounted(() => {
  // Lógica para separar el prefijo y el número si viene un valor inicial
  const initialValue = props.modelValue || ''
  const withPrefix = prefixes.find(p => initialValue.startsWith(p.value)) 
  const foundPrefix = withPrefix ?? prefixes.find(p => p.value === '+51')

  if (foundPrefix) {
    selectedPrefix.value = foundPrefix
    phoneNumber.value = withPrefix ? initialValue.substring(foundPrefix.value.length) : initialValue
  }
})
</script>

<template>
  <q-input v-model="phoneNumber" :placeholder="placeholder" borderless clearable dense class="form__inputsSelect mt-2"
    :rules="rules">
    <template v-slot:prepend>
      <q-select v-model="selectedPrefix" :options="filteredPrefixes" @filter="filterFn" input-debounce="0" borderless
        emit-value map-options dense class="prefixInput" style="min-width:0.5rem">
        <template v-slot:option="scope">
          <q-item v-bind="scope.itemProps">
            <q-item-section avatar>
              <span>{{ scope.opt.flag }}</span>
            </q-item-section>
            <q-item-section>
              <q-item-label>{{ scope.opt.label }}</q-item-label>
            </q-item-section>
          </q-item>
        </template>
      </q-select>
    </template>
  </q-input>
</template>

<style lang="scss">
.prefixInput {
  & .q-field__inner {

    padding: 0px 0.7rem !important;
    box-shadow: 0px 3px 5px 0px #bfbfbf00 !important;
    border-radius: 0rem !important;
    padding-right: 0rem !important;

    width: 30% !important;

    & .q-field__native {
      padding-top: 10px;
    }

    & .q-field__input {
      display: none;
    }

  }
}

.form__inputsSelect {
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
    overflow: hidden;
  }
}

@media (max-width: 780px) {
  .form__inputsSelect {
    & .q-field__inner {
      padding: 0px 0rem;

      padding-right: 0.5rem;
    }
  }
}
</style>