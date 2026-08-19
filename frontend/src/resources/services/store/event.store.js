import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useEventStore = defineStore('Event', {
  actions: {
    async getEvents(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/events' + (query ? `?${query}` : '');
        ApiService.get(url)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });

      })
    },
    async createEvent(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/events', data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            if (response.data.code == 403) {
              reject(response.data);
            }
            reject(response.data.error);
          });

      })

    },
    async createEventPay(postData) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/pays/events', postData)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });

      })

    },

    async getEventById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/events/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });

      })
    },
    async getEventAttendance(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/events/attendance/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });

      })
    },
    async getEventsByArea(area) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/events/byArea/' + area)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });

      })
    },

    async updateEvent(data, id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/events/' + id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            if (response.data.code == 403) {
              reject(response.data);
            }
            reject(response.data.error);
          });

      })

    },
    async getAvailableEventInDayByArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/events/availableBooking/' + data.idArea + '?date=' + data.date + '&')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });
      })

    },
    async deleteEvent(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.delete('/api/events/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });
      })
    },
    async cancelEvent(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/events/cancel/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });
      })
    },
    async getPendingEvent() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/events/pendings')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });
      })
    },
    async setAssitByData(id, data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/events/set-assists/' + id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(response.data.error);
          });
      })
    },
    async sendReminderEvent(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/events/send-reminder/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
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
