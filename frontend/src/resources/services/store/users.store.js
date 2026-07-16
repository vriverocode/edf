import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useUserStore = defineStore('User', {
  actions: {
    async createUser(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/users', data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async assingApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/users/assing_apartmet', data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async getResidents() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/users/get-resident')
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async createResident(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/users/temporary-or-resident', data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async getUserById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.get('/api/users/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response.data.error)
          })
      })
    },
    async getUsers(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const params = new URLSearchParams()
        if (data.page) params.set('page', String(data.page))
        if (data.per_page) params.set('per_page', String(data.per_page))
        if (data.search) params.set('search', String(data.search))
        if (data.rol) params.set('rol', String(data.rol))
        const qs = params.toString()
        ApiService.get('/api/users' + (qs ? '?' + qs : ''))
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response.data.error)
          })
      })
    },
    async deleteUser(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.delete('/api/users/d/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al eliminar usuario')
          })
      })
    },
    async getAllPendingsForAdmin() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/users/admin/get_pendings')
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response.data.error)
          })
      })
    },
    async completeFirstTime(formData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/users/complete-first-time', formData)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async updateResident(id, formData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.put('/api/users/resident/' + id, formData)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al actualizar usuario')
          })
      })
    },
    async getResidentBookings(userId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/users/resident/' + userId + '/bookings')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener reservas')
          })
      })
    },
  },
})