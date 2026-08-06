import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useBankAccountStore = defineStore('BankAccount', {
  actions: {
    async getAccounts() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/bank-accounts')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener cuentas bancarias')
          })
      })
    },
    async getAccountsByUser(userId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/bank-accounts?user_id=' + userId)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener cuentas bancarias')
          })
      })
    },
    async getAccountById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get(`/api/bank-accounts/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al obtener cuenta bancaria')
          })
      })
    },
    async createAccount(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/bank-accounts', data)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al crear cuenta bancaria')
          })
      })
    },
    async updateAccount(id, data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.put(`/api/bank-accounts/${id}`, data)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al actualizar cuenta bancaria')
          })
      })
    },
    async deleteAccount(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.delete(`/api/bank-accounts/${id}`)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response?.data?.error || 'Error al eliminar cuenta bancaria')
          })
      })
    },
  },
})
