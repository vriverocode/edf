import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const usePayMethodStore = defineStore('PayMehtod', {
  actions: {
    async getPayMethod() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/pay-method')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al obtener metodos de pago')
          })
      })
    },
    async createPayMethod(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/pay-method', data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data)
            }
            reject(response.data.error)
          })
      })
    },
    async getPayMethodById(id) {
        return await new Promise((resolve, reject) => {
          if (!ApiService.getToken()) throw ''
          ApiService.setHeader()
          ApiService.get(`/api/pay-method/byId/${id}`)
            .then(({ data }) => {
              if (data.code != 200) throw data
              resolve(data)
            })
            .catch(({ response }) => {
              reject(response?.data?.error || 'Error al obtener método de pago')
            })
        })
      },
  
      // --- NUEVO: Actualizar ---
      async updatePayMethod(id, data) {
        return await new Promise((resolve, reject) => {
          if (!ApiService.getToken()) throw ''
          ApiService.setHeader()
          ApiService.post(`/api/pay-method/u/${id}`, data)
            .then(({ data }) => {
              if (data.code != 200) throw data
              resolve(data)
            })
            .catch(({ response }) => {
              reject(response?.data?.error || 'Error al actualizar método de pago')
            })
        })
      },
      async disabledPayMethod(id, data) {
        return await new Promise((resolve, reject) => {
          if (!ApiService.getToken()) throw ''
          ApiService.setHeader()
          ApiService.post(`/api/pay-method/status/${id}`, data)
            .then(({ data }) => {
              if (data.code != 200) throw data
              resolve(data)
            })
            .catch(({ response }) => {
              reject(response?.data?.error || 'Error al actualizar método de pago')
            })
        })
      },
      
  },
})
