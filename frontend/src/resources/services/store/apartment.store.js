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
        const url = `/api/apartments?page=${data.page}&search=${data.search}&searchType=${data.filter}&type=${data.type}&number=${data.number}`
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
    async toggleTenantPaysQuota(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/apartments/toggle-tenant-quota/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error)
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
    async getApartmentsByFind(find, type = null, params = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const queryParams = new URLSearchParams()
        queryParams.append('find', find)
        if (type !== null && type !== undefined) {
          queryParams.append('type', type)
        }
        if (params && typeof params === 'object') {
          Object.entries(params).forEach(([key, val]) => {
            if (val !== null && val !== undefined && val !== '') {
              queryParams.append(key, val)
            }
          })
        }
        ApiService.get(`/api/apartments/byFind?${queryParams.toString()}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => reject(response?.data?.error || response?.data || response))
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
    async getInhabitedDepartments(page = 1, filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const params = new URLSearchParams({ page })
        if (filters.number) params.set('number', filters.number)
        if (filters.name) params.set('name', filters.name)
        ApiService.get(`/api/security/departments/inhabited?${params}`)
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