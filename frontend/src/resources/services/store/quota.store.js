import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useQuotaStore = defineStore('Quota', {
  actions: {
    async getQuotasByUser(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }

        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/quotas' + (query ? `?${query}` : '');

        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async getMonthlyGlobalQuotasForAdmin(filters) {
      return await this.getAdminGroupedByOwnerForMonth(filters?.month, { year: filters?.year, status: filters?.status });
    },
    async getAdminMonthlySummary(filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/quotas/admin/monthly-summary' + (query ? `?${query}` : '');
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response?.data?.error ?? response?.data);
          });
      });
    },
    async getAdminGroupedByOwnerForMonth(month, { year, status } = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const params = new URLSearchParams();
        if (year) params.set('year', String(year));
        if (status !== undefined && status !== null && status !== '' && Number(status) !== 4) {
          params.set('status', String(status));
        }
        const qs = params.toString();
        const url = `/api/quotas/admin/by-month/${month}` + (qs ? `?${qs}` : '');
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response?.data?.error ?? response?.data);
          });
      });
    },
    async createQuota(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/quotas', data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data);
            }
            reject(response.data.error);
          });

      })

    },
    async createQuotaPay(postData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/pays/quotas', postData.data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })

    },

    async getQuotaById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async getQuotaByMonth(month, data = null) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        let query = '';
        if (data != null) {
          query = `?year=${data.year}&owner=${data.owner}`
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/byMonth/' + month + query)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async getQuotaByPayId(payId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/byPay/' + payId)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async getClientWaterDetailByQuotaId(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/client-water-detail/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener detalle de medicion de agua');
          });
      })
    },
    async getClientMaintenanceDetailByQuotaId(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/client-maintenance-detail/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener detalle de mantenimiento');
          });
      })
    },
    async getQuotaByArea(area) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/byArea/' + area)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },

    async updateQuota(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.put('/api/quotas/' + data.id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            if (response.data.code == 403) {
              reject(response.data);
            }
            reject(response.data.error);
          });

      })

    },
    async getAvailableQuotaInDayByArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/availableBooking/' + data.idArea + '?date=' + data.date + '&')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })

    },
    async deleteQuota(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.delete('/api/quotas/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async cancelQuota(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/quotas/cancel/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async getPendingQuota() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/quotas/pendings')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    filterQuery(filter) {
      try {
        const params = new URLSearchParams();
        if (!filter || typeof filter !== 'object') return '';
        if (filter.status !== undefined && Number(filter.status) !== 4) params.set('status', String(filter.status));
        if (filter.area_id) params.set('area_id', String(filter.area_id));
        if (filter.date_from) params.set('date_from', String(filter.date_from));
        if (filter.date_to) params.set('date_to', String(filter.date_to));
        if (filter.amount_type) params.set('amount_type', String(filter.amount_type));
        if (filter.sort_by) params.set('sort_by', String(filter.sort_by));
        if (filter.sort_dir) params.set('sort_dir', String(filter.sort_dir));
        return params.toString();
      } catch (e) {
        return '';
      }
    }

  },
})
