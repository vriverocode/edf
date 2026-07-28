import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useApartmentStore = defineStore('Apartment', {
  actions: {
    async createApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.post('/api/apartments', data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },

    async getApartmentById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.get('/api/apartments/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response.data.error)
          })
      })
    },
    async getApartmentByUser() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.get('/api/apartments/byUser')
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response.data.error)
          })
      })
    },
    async getPaginationApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        // Agregamos el type a la petición GET
        const url = `/api/apartments?page=${data.page}&search=${data.search}&searchType=${data.filter}&type=${data.type}`
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response.data.error)
          })
      })
    },
    async deleteApartment(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/users/d/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response.data.error)
          })
      })
    },
    async updateApartment(id, data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/apartments/u/' + id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response.data.error)
          })
      })
    },
    async getOwnersWithoutApartment() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/users/without-apartment')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response.data.error)
          })
      })
    },
    async getApartmentsByFind(find, type = null) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        // Añadimos el type a la query string
        const typeParam = type ? `&type=${type}` : ''
        ApiService.get(`/api/apartments/byFind?find=${find}${typeParam}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => reject(response.data.error))
      })
    },

    async assignProperty(data) {
      // Nombre más global
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/users/assign-property', data)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => reject(response.data.error))
      })
    },
    async getInhabitedDepartments(page = 1) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get(`/api/security/departments/inhabited?page=${page}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response.data.error)
          })
      })
    },
    async getDepartmentResidents(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get(`/api/security/departments/${id}/residents`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response.data.error)
          })
      })
    },
  },
})