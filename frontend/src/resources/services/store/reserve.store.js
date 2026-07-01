import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useReserveStore = defineStore('Reserve', {
  actions: {
    async getReservesByUser(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/bookings' + (query ? `?${query}` : '');
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
    async getBookingsForSecurity(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/security/bookings' + (query ? `?${query}` : '');
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
    async createReserve(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/bookings', data)
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
    async createReservePay(postData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/pays/bookings', postData.data)
          .then(({ data }) => {
            if (data.code != 200) throw data

            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response.data.error)
          })

      })

    },

    async getReserveById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async getReservesByArea(area) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/byArea/' + area)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },

    async updateReserve(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.put('/api/bookings/' + data.id, data)
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
    async getAvailableReserveInDayByArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/availableBooking/' + data.idArea + '?date=' + data.date + '&')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })

    },
    async deleteReserve(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.delete('/api/bookings/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async cancelReserve(id, motive) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/bookings/cancel/' + id, { motive: motive })
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async cancelBookingForMaintenance(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/security/bookings/cancel-maintenance/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async getExtensionSlots(bookingId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/extension-slots/' + bookingId)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al obtener horarios de extensión');
          });
      })
    },
    async createExtension(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/bookings/extension', data)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error al crear extensión');
          });
      })
    },
    async getPendingReserve() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/pendings')
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
