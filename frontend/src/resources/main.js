import App from '@/App.vue'
import { createApp } from 'vue';
import quasarIconSet from 'quasar/icon-set/eva-icons'
import { createPinia } from 'pinia'

import router from '@/routes'

import {
  Quasar,
  Notify,
  Dialog,
  AddressbarColor
} from 'quasar'

import '@quasar/extras/eva-icons/eva-icons.css'
import mitt from 'mitt'
import 'quasar/src/css/index.sass'
import '@/assets/scss/app.scss'
import 'vant/lib/index.css';

const pinia = createPinia()

const myApp = createApp(App)
const emitter = mitt()
myApp.use(Quasar, {
  plugins: {
    Notify,
    Dialog,
    AddressbarColor
  },
  iconSet: quasarIconSet,
})

myApp.use(pinia)
myApp.use(router)

myApp.provide('emitter', emitter)

myApp.mount('#app')

