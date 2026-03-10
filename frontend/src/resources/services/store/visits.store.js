import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useVisitStore = defineStore('Visit', {
  actions: {
    async getVisitsByUser() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.get('/api/visits')
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            if (response?.data?.code == 403) {
              reject(response.data)
            }
            reject(response?.data?.error || 'Error al obtener visitas')
          })
      })
    },
    async storeVisit(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        ApiService.post('/api/visits', data)
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
  },
})
