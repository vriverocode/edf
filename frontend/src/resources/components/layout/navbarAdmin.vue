<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/services/store/auth.services'
import iconsApp from '@/assets/icons/index'
import { Capacitor } from '@capacitor/core'

const router = useRouter()
const route = useRoute()
const isNative = ref(Capacitor.isNativePlatform())
const emit = defineEmits(['logoutModal'])
const { user } = storeToRefs(useAuthStore())
const hoveredIndex = ref(-1)
const clickingIndex = ref(-1)

const logout = () => {
  emit('logoutModal')
}

const items = [
  { label: 'Inicio', icon: iconsApp.home3, route: '/dashboard' },
  { label: 'Usuarios', icon: iconsApp.user3, route: '/admin/users', roles: ['admin'] },
  { label: 'Finanzas', icon: iconsApp.finance2, route: '/admin/finance', roles: ['admin', 'propietario'] },
  { label: 'Perfil', icon: iconsApp.user3, route: '/profile/menu', roles: ['propietario'] },

  { label: 'Salir', icon: iconsApp.exit2, action: logout },
]

const visibleItems = computed(() => {
  return items.filter(item => {
    if (!item.roles) return true
    return item.roles.includes(user.value?.rol?.name?.toLowerCase())
  })
})

const activeIndex = computed(() => {
  const path = route.path
  return visibleItems.value.findIndex(item => item.route && path === item.route)
})

const blobIndex = computed(() => {
  if (hoveredIndex.value !== -1) return hoveredIndex.value
  if (activeIndex.value !== -1) return activeIndex.value
  return 0
})

const blobPositionStyle = computed(() => {
  const count = visibleItems.value.length
  return {
    width: `${100 / count}%`,
    transform: `translateX(${blobIndex.value * 100}%) translateY(-50%)`,
  }
})

const handleClick = (item, index) => {
  if (item.action) {
    item.action()
    return
  }
  if (route.path !== item.route) {
    clickingIndex.value = index
    setTimeout(() => { clickingIndex.value = -1 }, 300)
    router.push(item.route)
  }
}
</script>

<template>
  <nav class="bottom-tab" :class="{ 'spaceBarBottom': isNative, 'total-h': !isNative }">
    <div class="bottom-tab-inner">
      <div class="navbar__blob-track" :style="blobPositionStyle">
        <div class="navbar__blob" :class="{ 'navbar__blob--gooey': clickingIndex !== -1 }" />
      </div>
      <ul class="navbar__menu">
        <li
          v-for="(item, index) in visibleItems"
          :key="item.label"
          class="navbar__item"
          :class="{ 'navbar__item--active': blobIndex === index }"
          @mouseenter="hoveredIndex = index"
          @mouseleave="hoveredIndex = -1"
          @click="handleClick(item, index)"
        >
          <a class="navbar__link">
            <div class="navbar__icon" v-html="item.icon" />
            <span class="navbar__label">{{ item.label }}</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>
</template>

<style lang="scss">

$borderRadius: 8px;
$timing: 350ms;

@keyframes gooeyEffect {
  0% {
    transform: scaleX(1) scaleY(1);
  }
  50% {
    transform: scaleX(0.5) scaleY(1.5);
  }
  100% {
    transform: scaleX(1) scaleY(1);
  }
}

.navbar__item--active {
  .navbar__icon {
    svg path {
      stroke: #ffffff !important;
      fill: #ffffff !important;
    }
    .sharpcorners_een {
      fill: #ffffff !important;
    }
  }
  .navbar__label {
    color: #ffffff !important;
    font-weight: 700;
  }
}
</style>

<style lang="scss" scoped>

$text: #6a778e;
$borderRadius: 8px;
$timing: 350ms;

.bottom-tab {
  border-top: 1.5px solid #e0e0e0;
  width: 100%;
  z-index: 2;
  background: #ffffff;
}

.bottom-tab-inner {
  position: relative;
}

.navbar__menu {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
}

.navbar__item {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  position: relative;
  z-index: 1;
  padding: 6px 0;
}

.navbar__link {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  width: 100%;
}

.navbar__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
}

.navbar__label {
  font-size: 0.7rem;
  color: $text;
  transition: color $timing ease;
  line-height: 1.2;
}

.navbar__blob-track {
  position: absolute;
  top: 50%;
  height: 90%;
  left: 0;
  z-index: 0;
  pointer-events: none;
  transition: transform $timing cubic-bezier(1, 0.2, 0.1, 1.2);
}

.navbar__blob {
  width: 100%;
  height: 100%;
  background: $primary;
  border-radius: $borderRadius;

  &.navbar__blob--gooey {
    animation: gooeyEffect $timing ease 1;
  }
}

.spaceBarBottom {
  height: 10%;
}

.total-h {
  position: fixed;
  bottom: 0;
}

@media screen and (max-width: 820px) {
  .bottom-tab {
    width: 100%;
    left: 0;
  }
}
</style>
