import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useServiceCategoryStore = defineStore('ServiceCategories', {
  actions: {
    async getServiceCategories() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/service-categories')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener categorías de servicio')
          })
      })
    },

    async createServiceCategory(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/service-categories', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al crear categoría de servicio')
          })
      })
    },
  },
})
