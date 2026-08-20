import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useNotificationsStore = defineStore('Notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    pagination: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
    lastIncoming: null
  }),
  actions: {
    async fetch(params = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) { throw '' }
        ApiService.setHeader();
        const query = this.toQuery(params)
        const url = '/api/notifications' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            const page = data.data
            this.items = page.data
            this.pagination = {
              current_page: page.current_page,
              per_page: page.per_page,
              total: page.total,
              last_page: page.last_page
            }
            resolve(data)
          }).catch(({ response }) => {
            reject(response?.data?.error || 'Error al cargar notificaciones')
          })
      })
    },
    async fetchUnreadCount() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) { throw '' }
        ApiService.setHeader();
        ApiService.get('/api/notifications/unread-count')
          .then(({ data }) => {
            if (data.code != 200) throw data
            this.unreadCount = data.data.count || 0
            resolve(data)
          }).catch(({ response }) => {
            reject(response?.data?.error || 'Error al cargar contador')
          })
      })
    },
    async markAsRead(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) { throw '' }
        ApiService.setHeader();
        ApiService.post(`/api/notifications/${id}/mark-as-read`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            this.items = this.items.map(n => n.id === id ? { ...n, read_at: new Date().toISOString() } : n)
            this.unreadCount = Math.max(0, this.unreadCount - 1)
            resolve(data)
          }).catch(({ response }) => {
            reject(response?.data?.error || 'Error al marcar notificación')
          })
      })
    },
    async markAllAsRead() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) { throw '' }
        ApiService.setHeader();
        ApiService.post('/api/notifications/mark-all-as-read')
          .then(({ data }) => {
            if (data.code != 200) throw data
            this.items = this.items.map(n => ({ ...n, read_at: new Date().toISOString() }))
            this.unreadCount = 0
            resolve(data)
          }).catch(({ response }) => {
            reject(response?.data?.error || 'Error al marcar todas')
          })
      })
    },
    async deleteNotification(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) { throw '' }
        ApiService.setHeader();
        const notificationToDelete = this.items.find(n => n.id === id)
        const wasUnread = notificationToDelete && !notificationToDelete.read_at
        ApiService.delete(`/api/notifications/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            this.items = this.items.filter(n => n.id !== id)
            if (wasUnread) {
              this.unreadCount = Math.max(0, this.unreadCount - 1)
            }
            resolve(data)
          }).catch(({ response }) => {
            reject(response?.data?.error || 'Error al eliminar notificación')
          })
      })
    },
    onIncoming(notification) {
      // Estructura que llega desde BroadcastNotificationCreated
      this.items = [{ id: notification.id || crypto.randomUUID?.() || String(Date.now()), data: notification, read_at: null, created_at: new Date().toISOString() }, ...this.items]
      this.unreadCount += 1
      this.lastIncoming = notification
    },
    bindEchoListener(userId) {

      try {
        if (!window.Echo || !userId) return
        window.Echo.private(`App.Models.User.${userId}`).notification((notification) => {
          this.onIncoming(notification)
        })
      } catch (e) { }
    },
    toQuery(filter) {
      try {
        const params = new URLSearchParams()
        if (!filter || typeof filter !== 'object') return ''
        if (filter.status) params.set('status', String(filter.status))
        if (filter.page) params.set('page', String(filter.page))
        if (filter.per_page) params.set('per_page', String(filter.per_page))
        return params.toString()
      } catch (e) { return '' }
    }
  }
})


