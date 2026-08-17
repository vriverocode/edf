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
        async getMaintenancesBySearch(search) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                const query = this.filterQuery(search);
                ApiService.setHeader();
                ApiService.get(`/api/maintenances? ${query ? query : ''}`)
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
        },

        async deleteMaintenance(id) {
            return await new Promise((resolve, reject) => {
                if (!ApiService.getToken()) {
                    throw '';
                }
                ApiService.setHeader();
                ApiService.delete(`/api/maintenances/d/${id}`)
                    .then(({ data }) => {
                        if (data.code !== 200) throw data;
                        resolve(data);
                    })
                    .catch(({ response }) => {
                        console.error(response);
                        reject(response?.data?.error || 'Error al eliminar el mantenimiento');
                    });
            })
        },
        filterQuery(filter) {
            try {
                const params = new URLSearchParams();
                if (!filter || typeof filter !== 'object') return '';
                if (filter.page) params.set('page', String(filter.page));
                if (filter.per_page) params.set('per_page', String(filter.per_page));
                if (filter.status !== undefined && filter.status !== '') params.set('status', String(filter.status));
                if (filter.comun_area_id) params.set('comun_area_id', String(filter.comun_area_id));
                if (filter.date_from) params.set('date_from', String(filter.date_from));
                if (filter.date_to) params.set('date_to', String(filter.date_to));
                return params.toString();
            } catch (e) {
                return '';
            }
        }
    }
})