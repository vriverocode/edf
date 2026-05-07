<script setup>
import { onMounted, ref } from 'vue'
import { useNotificationsStore } from '@/services/store/notifications.store'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import iconsApp from '@/assets/icons/index'
const notifications = useNotificationsStore()
const router = useRouter()
const loading = ref(false)
const ready = ref(false)

const load = async (page = 1) => {
  loading.value = true

  try {
    await notifications.fetch({ page, per_page: 15 })
    await notifications.fetchUnreadCount()

    console.log(notifications.items)
  } finally {
    loading.value = false
    ready.value = true
  }
}

const openItem = (item) => {
  const url = item?.data?.url || item?.url
  if (url) router.push(url)
}

const markItem = async (item) => {
  if (!item.read_at) {
    await notifications.markAsRead(item.id)
  }
}

const markAll = async () => {
  await notifications.markAllAsRead()
}

const deleteItem = async (item) => {
  try {
    await notifications.deleteNotification(item.id)
    Notify.create({
      type: 'positive',
      message: 'Notificación eliminada',
      position: 'top',
      timeout: 2000
    })
  } catch (error) {
    Notify.create({
      type: 'negative',
      message: 'Error al eliminar notificación',
      position: 'top',
      timeout: 2000
    })
  }
}

const getNotificationIcon = (item) => {
  const title = (item?.data?.title || '').toLowerCase()
  if (title.includes('admin')) {
    return 'eva-calendar-outline'
  } else if (title.includes('notice') || title.includes('tablón')) {
    return 'eva-volume-up-outline'
  } else if (title.includes('maintenance') || title.includes('mantenimiento')) {
    return 'eva-credit-card-outline'
  } else if (title.includes('service') || title.includes('servicio')) {
    return 'eva-settings-outline'
  } else if (title.includes('quota') || title.includes('cuota')) {
    return 'eva-calendar-outline'
  }
  return item?.data?.meta?.icon || 'eva-bell-outline'
}

const getNotificationIconColor = (item) => {
  const title = (item?.data?.title || '').toLowerCase()
  if (title.includes('admin')) {
    return 'blue'
  } else if (title.includes('notice') || title.includes('tablón')) {
    return 'orange'
  } else if (title.includes('maintenance') || title.includes('mantenimiento')) {
    return 'red'
  } else if (title.includes('service') || title.includes('servicio')) {
    return 'primary'
  } else if (title.includes('quota') || title.includes('cuota')) {
    return 'amber-8'
  }
  return item.read_at ? 'grey' : 'primary'
}

const formatTimeAgo = (dateString) => {
  if (!dateString) return ''

  const now = new Date()
  const date = new Date(dateString)
  const diffInSeconds = Math.floor((now - date) / 1000)

  if (diffInSeconds < 60) {
    return '1 min'
  } else if (diffInSeconds < 3600) {
    const minutes = Math.floor(diffInSeconds / 60)
    return `${minutes} min`
  } else if (diffInSeconds < 86400) {
    const hours = Math.floor(diffInSeconds / 3600)
    return `${hours} hr`
  } else {
    const days = Math.floor(diffInSeconds / 86400)
    return `${days} día${days > 1 ? 's' : ''}`
  }
}
const descriptionByScreenSize = (item) => {
  return window.screen.width > 780
    ? `${item.data?.message || ''}`
    : `${item.data?.message ? item.data?.message.substr(0, 30) : ''}...`
}
onMounted(() => {
  load()
})
</script>

<template>
  <div class="h-full overflow-auto md:px-28 px-3 pb-6">
    <template v-if="ready">
      <div class="flex justify-end items-center pt-3">
        <q-btn color="primary" style="border-radius: 0.4rem;" no-caps @click="markAll"
          :disable="notifications.unreadCount === 0">
          <div class="px-2">
            Marcar todas como leídas
          </div>
        </q-btn>
      </div>
      <div v-if="notifications.items.length > 0">
        <div class="notification-list">
          <q-slide-item v-for="item in notifications.items" :key="item.id" @right="() => deleteItem(item)"
            right-color="red-8" class="my-3 notification-container" style="border-radius: 12px!important;">
            <template v-slot:right>
              <div class="row items-center" style="border-radius: 12px;">
                <q-icon name="eva-trash-2-outline" />
                <div class="ml-1 text-subtitle2">
                  Borrar notificación
                </div>
              </div>
            </template>

            <q-card class="notification-card q-mb-4 py-2 px-1" :class="{ 'notification-read': item.read_at }" flat
              bordered>
              <q-card-section class="q-py-none q-px-sm justify-between" horizontal>
                <!-- Icon Section -->
                <section class="flex firstPart" @click="openItem(item)">
                  <q-card-section class="notification-icon-section q-pa-0  q-px-none q-py-none flex flex-center">
                    <div :class="`bg-${getNotificationIconColor(item)}`" class="iconNotificationList p-3">
                      <q-icon :name="getNotificationIcon(item)" color="white" size="25px" />
                    </div>
                  </q-card-section>

                  <!-- Content Section -->
                  <q-card-section class="notification-content-section q-pa-0 q-px-none q-py-none  q-pl-sm"
                    style="flex: 1;">
                    <div class="flex justify-between items-start">
                      <div style="flex: 1;">
                        <div class="notification-title font-medium" :class="{ 'text-weight-bold': !item.read_at }">
                          {{ item.data?.title || 'Notificación' }}
                        </div>
                        <div class="notification-message mt-1">
                          {{ descriptionByScreenSize(item) }}
                        </div>
                      </div>

                    </div>
                  </q-card-section>
                </section>

                <!-- Action Button -->
                <q-card-section class="flex column items-end   q-pa-0 q-px-none q-py-none">
                  <div class="notification-time mb-0 ">
                    {{ formatTimeAgo(item.created_at) }}(s)
                  </div>
                  <div class="notification-action-section">
                    <q-btn flat round dense size="1.1rem" icon="eva-checkmark-circle-2-outline"
                      :color="item.read_at ? 'grey' : 'primary'" @click.stop="markItem(item)"
                      :disable="!!item.read_at" />
                    <div flat rounded color="primary" size="sm" class="ml-1 md:ml-3 cursor-pointer">
                      <div v-html="iconsApp.optionsBook"></div>
                      <q-menu>
                        <q-list style="min-width: 150px">
                          <q-item clickable v-close-popup @click.stop="markItem(item)" v-if="!item.read_at">
                            <q-item-section>Marcar como leida</q-item-section>
                          </q-item>
                          <q-separator />
                          <q-item clickable v-close-popup @click="deleteItem(item)">
                            <q-item-section>Borrar</q-item-section>
                          </q-item>
                          <q-separator />
                        </q-list>
                      </q-menu>
                    </div>
                  </div>
                </q-card-section>
              </q-card-section>
            </q-card>
          </q-slide-item>
        </div>

        <div class="flex justify-end py-4">
          <q-pagination v-model="notifications.pagination.current_page"
            :max="Math.ceil(notifications.pagination.total / notifications.pagination.per_page) || 1"
            @update:model-value="(p) => load(p)" color="primary" />
        </div>
      </div>
      <div v-else>
        <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
          <q-icon name="eva-bell-outline" size="4rem" color="grey-5" class="q-mb-md" />
          <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
            No hay notificaciones
          </div>


        </div>
      </div>
    </template>
    <template v-else>
      <div class="h-full flex flex-center">
        <q-spinner color="primary" size="4rem" :thickness="5" />
      </div>
    </template>
  </div>
</template>

<style lang="scss" scoped>
.firstPart {
  width: 90%;
}

.iconNotificationList {
  border-radius: 12px;
}

.notification-list {
  padding-top: 8px;
}

.notification-container {
  box-shadow: 0px 5px 5px 0px rgba(54, 54, 54, 0.082) !important;
  border-radius: 12px;

}

.notification-card {
  border-radius: 12px;
  transition: all 0.2s ease;
  cursor: pointer;

  &:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  }

  &.notification-read {
    opacity: 0.8;
  }
}

.notification-icon-section {
  display: flex;
  align-items: center;
  justify-content: center;
}

.notification-content-section {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.notification-title {
  font-size: 15px;
  color: #333;
  line-height: 1.3;
}

.notification-message {
  font-size: 13px;
  color: #666;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notification-time {
  font-size: 12px;
  color: #999;
  white-space: nowrap;
  font-weight: 500;
}

.notification-action-section {
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
