import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useTransactionCategoryStore = defineStore('TransactionCategories', {
  actions: {
    async getTransactionCategories(type = null) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        const query = type != null ? `?type=${type}` : ''
        ApiService.get(`/api/transaction-categories${query}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener categorías contables')
          })
      })
    },

    async createTransactionCategory(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/transaction-categories', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al crear categoría contable')
          })
      })
    },
  },
})
