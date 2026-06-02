import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useProviderStore = defineStore('Providers', {
  actions: {
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
    }
  }
})
