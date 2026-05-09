import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useMonthlyBillsStore = defineStore('MonthlyBills', {
  actions: {
    async createMonthlyBill(payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/monthly-bills', payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
              return
            }
            reject(response?.data?.error || 'Error al registrar presupuesto mensual')
          })
      })
    },

    async getMonthlyBills(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const query = this.filterQuery(filters)
        const url = '/api/monthly-bills' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al cargar presupuestos mensuales')
          })
      })
    },

    async checkBudgetExistsForPeriod(month, year) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const params = new URLSearchParams()
        params.set('month', String(month))
        params.set('year', String(year))
        ApiService.get('/api/monthly-bills/exists-for-period?' + params.toString())
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al verificar el presupuesto mensual')
          })
      })
    },

    async getMonthlyBillById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/monthly-bills/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al cargar el presupuesto mensual')
          })
      })
    },

    async updateMonthlyBill(id, payload) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/monthly-bills/u/' + id, payload)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
              return
            }
            reject(response?.data?.error || 'Error al actualizar el presupuesto mensual')
          })
      })
    },

    filterQuery(filter) {
      try {
        const params = new URLSearchParams()
        if (!filter || typeof filter !== 'object') return ''
        if (filter.page) params.set('page', String(filter.page))
        if (filter.per_page) params.set('per_page', String(filter.per_page))
        if (Array.isArray(filter.years) && filter.years.length > 0) {
          filter.years.forEach((y) => params.append('years[]', String(y)))
        }
        return params.toString()
      } catch (e) {
        return ''
      }
    }
  }
})

