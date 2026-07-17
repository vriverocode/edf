import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useReportStore = defineStore('Report', {
  actions: {
    async getBookings(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const query = this.filterQuery(filters)
        const url = '/api/reports/bookings' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al cargar reporte')
          })
      })
    },

    async getBookingsMetrics(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        ApiService.setHeader()
        const query = this.filterQuery(filters)
        const url = '/api/reports/bookings/metrics' + (query ? `?${query}` : '')
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al cargar métricas')
          })
      })
    },

    async exportBookings(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw ''
        }
        const token = ApiService.getToken()
        const query = this.filterQuery(filters)
        const url = import.meta.env.VITE_LARAVEL_API_URL + '/api/reports/bookings/export' + (query ? `?${query}` : '')
        const link = document.createElement('a')
        link.href = url
        link.setAttribute('Authorization', `Bearer ${token}`)
        fetch(url, { headers: { Authorization: `Bearer ${token}` } })
          .then((res) => {
            if (!res.ok) throw new Error('Error al exportar')
            return res.blob()
          })
          .then((blob) => {
            const downloadUrl = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = downloadUrl
            a.download = 'reporte-reservas.xlsx'
            document.body.appendChild(a)
            a.click()
            a.remove()
            window.URL.revokeObjectURL(downloadUrl)
            resolve(true)
          })
          .catch((err) => {
            console.log(err)
            reject('Error al descargar el archivo')
          })
      })
    },

    filterQuery(filter) {
      try {
        const params = new URLSearchParams()
        if (!filter || typeof filter !== 'object') return ''
        if (filter.search) params.set('search', String(filter.search))
        if (filter.status !== undefined && Number(filter.status) !== 4) params.set('status', String(filter.status))
        if (filter.include_cancelled) params.set('include_cancelled', '1')
        if (filter.area_id) params.set('area_id', String(filter.area_id))
        if (filter.date_from) params.set('date_from', String(filter.date_from))
        if (filter.date_to) params.set('date_to', String(filter.date_to))
        if (filter.sort_by) params.set('sort_by', String(filter.sort_by))
        if (filter.sort_dir) params.set('sort_dir', String(filter.sort_dir))
        if (filter.per_page) params.set('per_page', String(filter.per_page))
        return params.toString()
      } catch (e) {
        return ''
      }
    },
  },
})
