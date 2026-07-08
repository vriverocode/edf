import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useGuestListStore = defineStore('GuestList', {
  actions: {
    async getGuestsByBooking(bookingId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/bookings/' + bookingId + '/guests')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener invitados')
          })
      })
    },
    async addGuest(bookingId, guestData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/bookings/' + bookingId + '/guests', guestData)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al agregar invitado')
          })
      })
    },
    async updateGuest(id, guestData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.put('/api/guests/' + id, guestData)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al actualizar invitado')
          })
      })
    },
    async deleteGuest(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.delete('/api/guests/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al eliminar invitado')
          })
      })
    },
  },
})
