import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useProviderStore = defineStore('Providers', {
  actions: {
    async getProviders(params = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const query = this.filterQuery(params)
        const url = '/api/providers' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener proveedores')
          })
      })
    },

    async getProviderById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/providers/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener el proveedor')
          })
      })
    },

    async createProvider(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/providers', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al registrar el proveedor')
          })
      })
    },

    async updateProvider(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/providers/u/' + id, payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al actualizar el proveedor')
          })
      })
    },

    async deleteProvider(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.delete('/api/providers/d/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al eliminar el proveedor')
          })
      })
    },

    filterQuery(filter) {
      try {
        const params = new URLSearchParams()
        if (!filter || typeof filter !== 'object') return ''
        if (filter.page) params.set('page', String(filter.page))
        if (filter.per_page) params.set('per_page', String(filter.per_page))
        if (filter.search) params.set('search', String(filter.search))
        return params.toString()
      } catch (e) {
        return ''
      }
    }
  }
})
