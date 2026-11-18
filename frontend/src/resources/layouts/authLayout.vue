<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import logo from '@/assets/img/logo/logo-transparente.webp'

const route = useRoute()
const router = useRouter()

const isAuthShell = computed(() => route.meta?.authShell === true)
const showAuthTabs = computed(() => route.meta?.authTabs !== false)
const authTab = computed(() => (route.path === '/register' ? 'register' : 'login'))
const authTabs = [
  { label: 'Iniciar sesión', value: 'login', class: 'text-[0.9rem] font-bold text-brand-muted' },
  { label: 'Registrarse', value: 'register', class: 'text-[0.9rem] font-bold text-brand-muted' },
]

const changeTab = (value) => {
  if (!value || value === authTab.value) return
  router.push(value === 'register' ? '/register' : '/login')
}

// Animación de máquina de escribir para slogan_part1 y slogan_part2
const activeSlogan = ref(1)
const slogan1Line1 = ref('')
const slogan1Line2 = ref('')
const slogan2Part1 = ref('')
const slogan2Part2 = ref('')
const cursorLine = ref(1)
const isDeleting = ref(false)

let isTypewriterActive = true
let typewriterTimeout = null

const typewriterSleep = (ms) =>
  new Promise((resolve) => {
    if (!isTypewriterActive) return
    typewriterTimeout = setTimeout(() => {
      if (isTypewriterActive) resolve()
    }, ms)
  })

const runTypewriter = async () => {
  const S1_L1 = 'Tu condominio,'
  const S1_L2 = 'más facil.'
  const S2_P1 = 'Easy'
  const S2_P2 = 'Condo'

  const TYPE_SPEED = 75
  const DELETE_SPEED = 35
  const PAUSE_READ = 4500
  const PAUSE_TRANSITION = 600

  while (isTypewriterActive) {
    // Slogan 1
    isDeleting.value = false
    activeSlogan.value = 1
    cursorLine.value = 1
    slogan1Line1.value = ''
    slogan1Line2.value = ''

    for (let i = 1; i <= S1_L1.length; i++) {
      if (!isTypewriterActive) return
      slogan1Line1.value = S1_L1.slice(0, i)
      await typewriterSleep(TYPE_SPEED)
    }

    cursorLine.value = 2
    await typewriterSleep(140)

    for (let i = 1; i <= S1_L2.length; i++) {
      if (!isTypewriterActive) return
      slogan1Line2.value = S1_L2.slice(0, i)
      await typewriterSleep(TYPE_SPEED)
    }

    // Queda quieto varios segundos mostrando el texto completo
    await typewriterSleep(PAUSE_READ)

    // Borrado con fade suave
    isDeleting.value = true

    for (let i = S1_L2.length - 1; i >= 0; i--) {
      if (!isTypewriterActive) return
      slogan1Line2.value = S1_L2.slice(0, i)
      await typewriterSleep(DELETE_SPEED)
    }

    cursorLine.value = 1
    await typewriterSleep(90)

    for (let i = S1_L1.length - 1; i >= 0; i--) {
      if (!isTypewriterActive) return
      slogan1Line1.value = S1_L1.slice(0, i)
      await typewriterSleep(DELETE_SPEED)
    }

    await typewriterSleep(PAUSE_TRANSITION)

    // Slogan 2
    isDeleting.value = false
    activeSlogan.value = 2
    slogan2Part1.value = ''
    slogan2Part2.value = ''

    for (let i = 1; i <= S2_P1.length; i++) {
      if (!isTypewriterActive) return
      slogan2Part1.value = S2_P1.slice(0, i)
      await typewriterSleep(TYPE_SPEED)
    }

    for (let i = 1; i <= S2_P2.length; i++) {
      if (!isTypewriterActive) return
      slogan2Part2.value = S2_P2.slice(0, i)
      await typewriterSleep(TYPE_SPEED)
    }

    // Queda quieto varios segundos mostrando "EasyCondo"
    await typewriterSleep(PAUSE_READ)

    // Borrado con fade suave
    isDeleting.value = true

    for (let i = S2_P2.length - 1; i >= 0; i--) {
      if (!isTypewriterActive) return
      slogan2Part2.value = S2_P2.slice(0, i)
      await typewriterSleep(DELETE_SPEED)
    }

    for (let i = S2_P1.length - 1; i >= 0; i--) {
      if (!isTypewriterActive) return
      slogan2Part1.value = S2_P1.slice(0, i)
      await typewriterSleep(DELETE_SPEED)
    }

    await typewriterSleep(PAUSE_TRANSITION)
  }
}

onMounted(() => {
  runTypewriter()
})

onUnmounted(() => {
  isTypewriterActive = false
  if (typewriterTimeout) clearTimeout(typewriterTimeout)
})
</script>

<template>
  <section class="authContainer">
    <template v-if="isAuthShell">
      <div
        class="auth-login relative flex h-full w-full justify-center overflow-x-hidden overflow-y-auto bg-brand-cream pb-[env(safe-area-inset-bottom,0px)] font-sans"
      >
        <span
          class="pointer-events-none fixed -right-[110px] -top-[120px] z-0 h-[300px] w-[300px] rounded-full bg-brand-green/[0.08] blur-[2px]"
          aria-hidden="true"
        ></span>
        <span
          class="pointer-events-none fixed -bottom-[140px] -left-[120px] z-0 h-[340px] w-[340px] rounded-full bg-brand-terracotta/[0.07] blur-[2px]"
          aria-hidden="true"
        ></span>

        <div class="relative z-[1] w-full max-w-[480px] md:my-auto">
          <header
            class="relative isolate overflow-hidden rounded-b-[2rem] bg-brand-green px-6 pb-[5.75rem]
            pt-[calc(1.5rem_+_env(safe-area-inset-top,0px))]
            text-white md:rounded-b-none md:rounded-t-[1.75rem] md:px-6 md:pb-[4.8rem] md:pt-8"
          >
            <span
              class="pointer-events-none absolute -right-[60px] -top-[80px] -z-10 h-[210px] w-[210px] rounded-full bg-brand-sage/30"
              aria-hidden="true"
            ></span>
            <span
              class="pointer-events-none absolute -bottom-[70px] -left-[80px] -z-10 h-[180px] w-[180px] rounded-full bg-white/5"
              aria-hidden="true"
            ></span>
            <span
              class="pointer-events-none absolute right-2 top-[6.5rem] -z-10 h-[92px] w-[92px] rotate-[18deg] rounded-[1.25rem] bg-brand-sand/55"
              aria-hidden="true"
            ></span>

            <div class="inline-flex items-center gap-[0.65rem]">
              <div class="shrink-0" aria-hidden="true">
                <img :src="logo" class="h-28 w-28 object-contain md:h-36 md:w-36" />
              </div>
              <div class="min-h-[4.5rem] flex flex-col justify-center">
                <Transition name="slogan-fade" mode="out-in">
                  <!-- Slogan 1: Tu condominio, más facil. -->
                  <div
                    v-if="activeSlogan === 1"
                    key="slogan1"
                    class="slogan_part1 flex flex-col justify-center"
                    :class="{ 'opacity-70 transition-opacity duration-300': isDeleting }"
                  >
                    <div class="text-2xl sm:text-3xl font-bold leading-tight">
                      {{ slogan1Line1 }}<span v-if="cursorLine === 1" class="typewriter-cursor">|</span>
                    </div>
                    <div
                      class="text-2xl sm:text-3xl font-bold leading-tight text-brand-amber min-h-[2rem] sm:min-h-[2.25rem]"
                    >
                      {{ slogan1Line2 }}<span v-if="cursorLine === 2" class="typewriter-cursor">|</span>
                    </div>
                  </div>

                  <!-- Slogan 2: EasyCondo -->
                  <div
                    v-else
                    key="slogan2"
                    class="slogan_part2 flex items-center min-h-[4.5rem]"
                    :class="{ 'opacity-70 transition-opacity duration-300': isDeleting }"
                  >
                    <div class="text-3xl sm:text-3xl font-bold leading-tight">
                      <span class="font-bold text-3xl poppins-bold">{{ slogan2Part1 }}</span
                      ><span class="text-brand-amber text-3xl font-bold poppins-bold">{{
                        slogan2Part2
                      }}</span
                      ><span class="typewriter-cursor">|</span>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </header>

          <section
            class="relative z-[2] -mt-[5.5rem] mx-4 mb-4 rounded-3xl bg-white p-5 pt-8 shadow-[0_24px_48px_rgba(34,56,41,0.14)] md:mx-0 md:mt-[-4.75rem] md:p-6"
          >
            <q-btn-toggle
              v-if="showAuthTabs"
              :model-value="authTab"
              :options="authTabs"
              no-caps
              unelevated
              rounded
              spread
              toggle-color="primary"
              toggle-text-color="white"
              class="auth-tabs w-full rounded-full bg-brand-field p-1"
              @update:model-value="changeTab"
            />

            <router-view v-slot="{ Component }">
              
                <component :is="Component" :key="route.path" />
             
            </router-view>
          </section>
        </div>
      </div>
    </template>

    <router-view v-else />
  </section>
</template>

<style>
.authContainer {
  height: 100%;
  overflow: hidden;
  background: #f7f1e4;
}

.auth-login :where(h1, p, span, label, a, button, input) {
  font-family: 'Figtree', sans-serif;
}

.auth-field .q-field__control {
  @apply min-h-[52px] rounded-[0.9rem] bg-brand-field px-3.5;
  transition: box-shadow 0.2s ease, background-color 0.2s ease;
}

.auth-field .q-field__control::before,
.auth-field .q-field__control::after {
  border: 0 !important;
}

.auth-field.q-field--focused .q-field__control {
  @apply bg-[#FBF6EA] ring-2 ring-brand-amber/45;
}

.auth-field.q-field--error .q-field__control {
  @apply ring-2 ring-[#B4452F]/30;
}

.auth-tabs .q-btn-item {
  border-radius: 9999px !important;
}

.fade-enter-active,
.fade-leave-active {
  @apply transition-opacity duration-200;
}

.fade-enter-from,
.fade-leave-to {
  @apply opacity-0;
}

.typewriter-cursor {
  display: inline-block;
  margin-left: 2px;
  color: #e3a33c;
  font-weight: 300;
  animation: typewriter-fade 0.9s ease-in-out infinite;
}

@keyframes typewriter-fade {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.15;
  }
}

.slogan-fade-enter-active,
.slogan-fade-leave-active {
  transition:
    opacity 0.45s ease,
    transform 0.45s ease;
}

.slogan-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.slogan-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
