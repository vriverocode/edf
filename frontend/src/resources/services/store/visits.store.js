import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useVisitStore = defineStore('Visit', {
  actions: {
    buildQueryParams(filters = {}) {
      const queryParams = new URLSearchParams()
      if (filters.search) queryParams.append('search', filters.search)
      if (filters.departament_id) queryParams.append('departament_id', filters.departament_id)
      if (Array.isArray(filters.status) && filters.status.length > 0) {
        filters.status.forEach((status) => queryParams.append('status[]', status))
      }
      return queryParams
    },

    async getVisitsByUser(filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const queryParams = this.buildQueryParams(filters)

        ApiService.get('/api/visits', queryParams.toString() ? `?${queryParams.toString()}` : '')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al obtener visitas')
          })
      })
    },

    async storeVisit(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/visits', data)
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
    async getVisitsForSecurity(filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const queryParams = this.buildQueryParams(filters)

        ApiService.get('/api/security/visits', queryParams.toString() ? `?${queryParams.toString()}` : '')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al obtener visitas')
          })
      })
    },
    async getAirbnbReserve(filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const queryParams = this.buildQueryParams(filters)

        ApiService.get('/api/security/airbnb', queryParams.toString() ? `?${queryParams.toString()}` : '')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al obtener visitas')
          })
      })
    },
    async getVisitFilterOptionsByUser() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/visits/filter-options')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener filtros')
          })
      })
    },
    async getVisitFilterOptionsForSecurity() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/security/visits/filter-options')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener filtros')
          })
      })
    },
    async getAirbnbFilterOptionsForSecurity() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/security/airbnb/filter-options')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener filtros')
          })
      })
    },
    async markVisitArrived(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post(`/api/security/visits/arrived/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al actualizar visita')
          })
      })
    },
  },
})
