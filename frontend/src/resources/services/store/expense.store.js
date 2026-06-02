import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useExpenseStore = defineStore('Expenses', {
  actions: {
    async getExpenses(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const query = this.filterQuery(filters)
        const url = '/api/expenses' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al cargar gastos')
          })
      })
    },

    async getExpenseFormOptions() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/expenses/form-options')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al cargar opciones del formulario')
          })
      })
    },

    async getExpenseById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/expenses/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al cargar el gasto')
          })
      })
    },

    async createExpense(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/expenses', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al registrar el gasto')
          })
      })
    },

    async updateExpense(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/expenses/u/' + id, payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al actualizar el gasto')
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
        if (filter.status) params.set('status', String(filter.status))
        return params.toString()
      } catch (e) {
        return ''
      }
    }
  }
})
