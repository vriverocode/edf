import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useMaintenanceStore = defineStore('Maintenance', {
  actions: {
    async getMaintenances() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/maintenances')
          .then(({ data }) => {
            if (data.code !== 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            console.error(response);
            reject(response?.data?.error || 'Error al obtener mantenimientos');
          });
      })
    },

        async createMaintenance(formData) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                // Mandamos el objeto nativo o FormData según corresponda
                ApiService.post('/api/maintenances', formData)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        if (response?.data?.code === 403) {
                            reject(response.data);
                        }
                        reject(response?.data?.error || 'Error al programar el mantenimiento');
                    });
            })
        },

        async getMaintenanceByArea(areaId, date) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.get(`/api/maintenances/by-area/${areaId}?date=${date}`)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data.data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al consultar mantenimientos');
                    });
            })
        },

        async getMaintenance(id) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.get(`/api/maintenances/${id}`)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data.data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al obtener el mantenimiento');
                    });
            })
        },

        async completeMaintenance(id, formData) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.post(`/api/maintenances/${id}/complete`, formData)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al completar el mantenimiento');
                    });
            })
        },

        async updateMaintenance(id, formData) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.post(`/api/maintenances/${id}/update`, formData)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al actualizar el mantenimiento');
                    });
            })
        },

        async changeMaintenanceStatus(id, data) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.post(`/api/maintenances/${id}/status`, data)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al cambiar el estado del mantenimiento');
                    });
            })
        }
    }
})