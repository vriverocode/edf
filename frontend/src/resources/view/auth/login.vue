<script setup>
import { reactive, ref } from 'vue';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import tutorial from '@/components/login/tutorial.vue';
import storage from '@/services/storage'
import bg from '@/assets/img/backgrounds/bg.webp'
import logoWite from '@/assets/img/logo/logo-white.webp';
const authServices = useAuthStore()
const tutorialView = ref(storage.getItem('tutorial'))

const login = reactive({
  username: '',
  password: ''
})

const isPwd = ref('true')
const loading = ref(false)
const router = useRouter();
const error = ref(false)
const errorMessage = ref('')
const rules = (id) => {
  if (id == 'user') return [
    val => val && val.length > 0 || 'Usuario no puede quedar vacio',
    val => (/[,$}#*. %"'()\-;&|<>]/.test(val) == false) || 'No debe contener "[](),%|&;\'" ',
  ]
  if (id == 'password') return [
    val => val && val.length > 0 || 'Contraseña no puede quedar vacio',
    val => val.length >= 8 || 'Debe tener 8 caracteres minimo'
  ]

}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    position: 'top',
    timeout: 2000
  })
}
const authLogin = () => {
  loading.value = true

  authServices.login(login)
    .then((response) => {
      errorMessage.value = response

      if (response.status !== 200) {
        throw response
      }
      showNotify('positive', 'Inicio de sesión correcto')
      setTimeout(() => {
        loading.value = false
        router.push('/dashboard')
      }, 2000)
    })
    .catch((response) => {
      showNotify('negative', response.status == 505 ? response.data.error : 'Error de conexión')
      loading.value = false;
      error.value = true
      // errorMessage.value = response
      console.log(error.value)
      console.log(response.data)

    })
}
const endTutorial = () => {
  tutorialView.value = 'true'
}
</script>
<template>
  <div class="h-full bg-white login-root">
      <Transition name="fade" mode="out-in">
        <div class="h-full md:w-1/2 md:mx-auto relative" v-if="tutorialView == 'true'">
          
          <div class="absolute inset-0 login__bg" :style="{ backgroundImage: 'url(' + bg + ')' }"></div>

          <div class="relative z-10 h-full md:w-full">
            <section class="mt-0 md:pt-12 ">
              <q-form @submit="authLogin" class="w-full h-full">
                <div class="mx-auto form__cont md:px-8">
                  <div class="w-full h-full">
                    <div class="relative md:px-10 px-8 h-full w-full form pt-12 md:pt-0">
                      <img :src="logoWite" alt="logo" class="md:w-1/6 w-2/5 mx-auto mt-12" />
                      <div class="text-white mt-5 text-center" style="font-weight:600; font-size: 1.6rem">
                        INGRESO
                      </div>
                      <div class="w-full mt-6 md:mt-8 ">
                        <q-input class="auth_input" color="white" v-model="login.username" :rules="rules('user')" rounded
                          standout>
                          <template v-slot:prepend>
                            <div class="pl-2" style="font-size:1rem; font-weight:500">Usuario</div>
                          </template>
                        </q-input>
                        <q-input class="q-pt-lg auth_input" color="grey-1" v-model="login.password"
                          :rules="rules('password')" placeholder="••••••••••" :type="isPwd ? 'password' : 'text'" rounded
                          standout>
                          <template v-slot:prepend>
                            <div class="pl-2" style="font-size:1rem; font-weight:500">Contraseña</div>
                          </template>
                          <template v-slot:append>
                            <q-icon :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'" class="cursor-pointer"
                              color="grey-1" @click="isPwd = !isPwd" />
                          </template>
                        </q-input>
                        <p class="mt-2 cursor-pointer md:mt-8 text-white text-subtitle1">¿Perdió su contraseña?</p>
                      </div>
                    </div>
                    <div class="md:px-16 px-6 mt-5 flex justify-center">
                      <q-btn flat class="btn__login w-auto md:w-1/2" no-caps="" :loading="loading" size="lg"
                        type="submit">
                        <div class="text-h6 text-bold md:px-2 px-12 text-white">Ingresar</div>
                      </q-btn>
                    </div>
                  </div>
                </div>
              </q-form>
            </section>
          </div>
        </div>
      </Transition>
      
      <Transition name="fade" mode="out-in">
        <div class="h-full" v-if="tutorialView != 'true'">
          <tutorial @end="endTutorial()" />
        </div>
      </Transition>
      
      <q-dialog v-model="error">
        <q-card class="">
          <q-card-section>
            <div class="text-h6">errorss</div>
          </q-card-section>
          <q-card-section class="q-pt-none text-red-500">
            {{ errorMessage }}
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat label="OK" color="primary" v-close-popup />
          </q-card-actions>
        </q-card>
      </q-dialog>
  </div>
</template>


<style lang="scss">
.login-root {
  backface-visibility: hidden;
  transform: translateZ(0);
  will-change: transform;
}

/* 2. Reemplazamos tu antiguo .login__container por .login__bg */
.login__bg {
  background-size: 100% 100% !important;
  background-position: bottom;
  background-repeat: no-repeat;
  /* Aceleramos la imagen de forma aislada */
  transform: translateZ(0); 
  will-change: transform;
}
.auth_input {
  & .q-field__control {
    background: white;
  }

  &.q-field--standout.q-field--highlighted .q-field__control {
    background: white;
  }

  &.q-field--standout .q-field__native {
    //color: #9b9b9b;
    color: rgb(0, 0, 0) !important;
    font-size: 1rem !important;

    &::placeholder {
      color: #9b9b9b !important;

    }

  }

  &.q-field--standout .q-icon:before {
    color: darkgray;
  }

  &.q-field--standout .q-field__prepend {
    color: rgb(0, 0, 0) !important;
    font-weight: 500;
    font-size: 0.95rem !important;
  }

  & .q-field__bottom--animated {
    padding-top: 6px;

    &>.col>div {
      background: white;
      width: max-content;
      padding: 0.6rem;
      border-radius: 0.3rem;
    }
  }
}

.login__container {
  background-size: 100% 100% !important;
  background-position: bottom;
  background-repeat: no-repeat;

}

.btn__login {
  --tw-bg-opacity: 1;
  background-color: #c8a34b;
  box-shadow: 0px .2rem 1rem 0px rgba(0, 0, 0, 0.345);
  transition: all 0.5s ease;
  border-radius: 1rem;
  padding: 0.8rem;
  font-weight: 800;
  min-width: 2.5em;
  min-height: 2.6em;

  & .icon_login_b {
    transition: all 1s ease;
    display: block;
  }

  &:hover {
    --tw-bg-opacity: 1;
    background-color: white;

    & .icon_login_b {
      color: rgb(2 132 199 / var(--tw-bg-opacity, 1)) !important;

    }
  }

  & .q-spinner {
    color: white !important;
  }

}

.form {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

@media (max-width: 780px) {
  .btn__login {
    border-radius: 5rem;

    & .icon_login_b {
      display: none;
    }

    &:hover {
      --tw-bg-opacity: 1;

      color: white;
      --tw-bg-opacity: 1;
      background-color: rgb(2 132 199 / var(--tw-bg-opacity, 1));

    }
  }
}
</style>