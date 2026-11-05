import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useMultaStore = defineStore('Multas', {
  actions: {
    async getMultas(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const params = new URLSearchParams()
        if (filters?.page) params.set('page', String(filters.page))
        if (filters?.per_page) params.set('per_page', String(filters.per_page))
        if (filters?.departament_id) params.set('departament_id', String(filters.departament_id))
        if (filters?.rule_id) params.set('rule_id', String(filters.rule_id))
        if (filters?.status !== undefined && filters?.status !== null && filters?.status !== '') {
          params.set('status', String(filters.status))
        }
        if (filters?.type) params.set('type', String(filters.type))
        if (filters?.search) params.set('search', filters.search)
        if (filters?.date_from) params.set('date_from', filters.date_from)
        if (filters?.date_to) params.set('date_to', filters.date_to)
        if (filters?.sort_by) params.set('sort_by', filters.sort_by)
        if (filters?.sort_dir) params.set('sort_dir', filters.sort_dir)

        const query = params.toString()
        ApiService.get('/api/multas' + (query ? `?${query}` : ''))
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar multas')
          })
      })
    },

    async getMultaById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get(`/api/multas/byId/${id}`)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar multa')
          })
      })
    },

    async createMulta(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/multas', payload)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al crear multa')
          })
      })
    },

    async updateMulta(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post(`/api/multas/u/${id}`, payload)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al actualizar multa')
          })
      })
    },

    async deleteMulta(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post(`/api/multas/d/${id}`)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al eliminar multa')
          })
      })
    },

    async getRules(params = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const queryParams = new URLSearchParams()
        if (params.search) queryParams.set('search', params.search)
        if (params.active !== undefined) queryParams.set('active', String(params.active))
        queryParams.set('per_page', '100')
        const query = queryParams.toString()
        ApiService.get('/api/rules' + (query ? `?${query}` : ''))
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar normas')
          })
      })
    },
  },
})

