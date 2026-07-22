<script setup>
import { onMounted, ref } from 'vue';
import { Notify } from 'quasar'
import { useUserStore } from '@/services/store/users.store';
import { useRoute, useRouter } from 'vue-router';
import phoneNumberInput from '@/components/layout/phoneNumberInput.vue';

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const loading = ref(false)
const loadUser = ref(false)
const formData = ref({
  name: '',
  username: '',
  email: '',
  phone: '',
  password: '',
})

const loadUserData = () => {
  loadUser.value = true
  userStore.getUserById(route.params.id)
    .then((response) => {
      if (response.code !== 200) throw response
      const u = response.data
      formData.value.name = u.name || ''
      formData.value.username = u.username || ''
      formData.value.email = u.email || ''
      formData.value.phone = u.phone || ''
    })
    .catch(() => {
      showNotify('negative', 'Error al cargar datos del usuario')
    })
    .finally(() => {
      loadUser.value = false
    })
}

const submit = () => {
  loading.value = true
  const payload = { ...formData.value }
  if (!payload.password?.trim()) delete payload.password
  userStore.updateUser(route.params.id, payload)
    .then(() => {
      showNotify('positive', 'Usuario actualizado correctamente')
      setTimeout(() => router.push('/admin/users/list'), 1000)
    })
    .catch((err) => {
      showNotify('negative', err || 'Error al actualizar usuario')
    })
    .finally(() => {
      loading.value = false
    })
}

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

onMounted(loadUserData)
</script>
<template>
  <div class="md:px-20 px-2">
    <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
      Editar usuario
    </div>
    <div v-if="loadUser" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>
    <q-form v-else @submit="submit()">
      <div class="row w-full">
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Nombre completo <span class="text-negative">*</span>
          </div>
          <q-input borderless dense clearable v-model="formData.name" class="form__inputsCR mt-2" color="primary"
            :rules="[val => val && val.length > 0 || 'Nombre es requerido']" />
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Nombre de usuario <span class="text-negative">*</span>
          </div>
          <q-input borderless dense clearable v-model="formData.username" class="form__inputsCR mt-2" color="primary"
            :rules="[val => val && val.length > 0 || 'Nombre de usuario es requerido']" />
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Correo electronico
          </div>
          <q-input borderless dense clearable v-model="formData.email" class="form__inputsCR mt-2" color="primary" />
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Contraseña <span class="text-negative">*</span>
          </div>
          <q-input borderless dense clearable v-model="formData.password" class="form__inputsCR mt-2" color="primary"
            type="password"
            :rules="[]" />
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Telefono
          </div>
          <phoneNumberInput v-model="formData.phone" label="Tu Teléfono" placeholder="997 123 456"
            class="phoneUser" />
        </div>
        <div class="col-12 my-2 px-2 md:px-12 pb-8 flex justify-end q-gutter-sm">
          <q-btn color="grey-7" style="border-radius: 0.5rem;" @click="router.push('/admin/users/list')">
            <div class="px-6 py-1">Cancelar</div>
          </q-btn>
          <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
            <div class="px-10 py-1">Guardar</div>
          </q-btn>
        </div>
      </div>
    </q-form>
  </div>
</template>
<style lang="scss">
.phoneUser.form__inputsSelect .prefixInput .q-field__inner {
  border: 0px solid rgb(223, 223, 223);
}
.form__inputsCR {
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}
@media (max-width: 780px) {
  .form__inputsCR .q-field__inner {
    padding: 0px 1rem;
  }
}
</style>
