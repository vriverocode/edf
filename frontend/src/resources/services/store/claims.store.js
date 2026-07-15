import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useClaimsStore = defineStore('Claims', {
  actions: {
    /**
     * Obtiene el número de secuencia actual del libro de reclamos.
     * @returns {Promise<number>} Número de reclamo correlativo
     */
    async getClaimSequence() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.get('/api/pays/claims/sequence')
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data.data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al obtener número de reclamo')
          })
      })
    },

    /**
     * Envía el formulario del libro de reclamos.
     * Acepta un FormData para soportar adjunto de voucher/imagen.
     * @param {FormData} formData
     * @returns {Promise}
     */
    async createClaim(formData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw ''
        ApiService.setHeader()
        ApiService.post('/api/pays/claims', formData)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al registrar el reclamo')
          })
      })
    },
  },
})
