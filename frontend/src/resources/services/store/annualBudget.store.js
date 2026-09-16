import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useAnnualBudgetStore = defineStore('AnnualBudget', {
  actions: {
    async getAnnualBudgets(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const params = new URLSearchParams()
        if (filters?.page) params.set('page', String(filters.page))
        if (filters?.per_page) params.set('per_page', String(filters.per_page))
        if (filters?.year) params.set('year', String(filters.year))
        const query = params.toString()
        ApiService.get('/api/annual-budgets' + (query ? `?${query}` : ''))
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar presupuestos anuales')
          })
      })
    },

    async getAnnualBudget(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get(`/api/annual-budgets/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar presupuesto anual')
          })
      })
    },

    async getAvailableExpenses() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/annual-budgets/available-expenses')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al cargar gastos disponibles')
          })
      })
    },

    async createAnnualBudget(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/annual-budgets', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al crear presupuesto anual')
          })
      })
    },

    async updateAnnualBudget(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post(`/api/annual-budgets/${id}`, payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al actualizar presupuesto anual')
          })
      })
    },

    async deleteAnnualBudget(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.delete(`/api/annual-budgets/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al eliminar presupuesto anual')
          })
      })
    },
  },
})
