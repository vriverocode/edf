import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useWaterReadingsStore = defineStore('WaterReadings', {
  actions: {
    async getNextPendingDepartment(month, year, currentDepartmentId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get(`/api/water-readings/next-pending?month=${month}&year=${year}&after=${currentDepartmentId}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener siguiente unidad pendiente')
          })
      })
    },
    async getWaterReadings(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const query = this.filterQuery(filters)
        const url = '/api/water-readings' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar mediciones de agua')
          })
      })
    },

    async getWaterReadingById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/water-readings/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar la medición')
          })
      })
    },

    async createWaterReading(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/water-readings', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al registrar medición de agua')
          })
      })
    },

    async updateWaterReading(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/water-readings/u/' + id, payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al actualizar medición de agua')
          })
      })
    },

    async getLastWaterReadingByDepartment(departmentId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/water-readings/last-by-department/' + departmentId)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al obtener la última medición')
          })
      })
    },

    filterQuery(filter) {
      try {
        const params = new URLSearchParams()
        if (!filter || typeof filter !== 'object') return ''
        if (filter.page) params.set('page', String(filter.page))
        if (filter.per_page) params.set('per_page', String(filter.per_page))
        if (filter.month) params.set('month', String(filter.month))
        if (filter.year) params.set('year', String(filter.year))
        return params.toString()
      } catch (e) {
        return ''
      }
    }
  }
})

