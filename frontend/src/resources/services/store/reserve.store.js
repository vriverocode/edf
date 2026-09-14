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
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener reservas del usuario');
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
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener reservas de seguridad');
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
            console.error(response)
            if (response.data.code == 403) {
              return reject(typeof response.data.message === 'string' ? { message: response.data.message } : { message: 'No autorizado' });
            }
            if (response.data.code == 409) {
              return reject({ message: typeof response.data.error === 'string' ? response.data.error : 'Límite de reservas por día alcanzado' });
            }
            reject({ message: typeof response?.data?.error === 'string' ? response.data.error : 'Error al crear la reserva' });
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
            console.error(response)
            reject({ message: typeof response?.data?.error === 'string' ? response.data.error : 'Error al registrar pago de reserva' })
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
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener la reserva');
          });

      })
    },
    async getReservesByArea(area, params = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(params);
        ApiService.get('/api/bookings/byArea/' + area + (query ? `?${query}` : ''))
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener reservas por área');
          });

      })
    },
    async getBookingsByDepartment(departamentId) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/byDepartment/' + departamentId)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener reservas por departamento');
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
            console.error(response)
            if (response.data.code == 403) {
              return reject(typeof response.data.message === 'string' ? { message: response.data.message } : { message: 'No autorizado' });
            }
            reject({ message: typeof response?.data?.error === 'string' ? response.data.error : 'Error al actualizar la reserva' });
          });

      })

    },
    async getAvailableReserveInDayByArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/bookings/availableBooking/' + data.idArea + '?date=' + data.date + '&' + 'reserve_type=' + data.reserveType + '&')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener disponibilidad');
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
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al eliminar la reserva');
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
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al cancelar la reserva');
          });
      })
    },
    async cancelBookingForMaintenance(id, motive) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/security/bookings/cancel-maintenance/' + id, { motive: motive })
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al cancelar mantenimiento');
          });
      })
    },
    async completeBooking(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/security/bookings/complete/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al completar la reserva');
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
            console.error(response)
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
            console.error(response)
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
            if (data.code !== 200) throw data;

            resolve(data);
          })
          .catch(({ response }) => {
            console.error(response)
            reject(typeof response?.data?.error === 'string' ? response.data.error : 'Error al obtener pendientes');
          });
      })
    },
    async getAllBookings(params) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const query = this.filterQuery(params);
        ApiService.get('/api/bookings/all' + (query ? `?${query}` : ''))
          .then(({ data }) => {
            if (data.code !== 200) throw data;

            resolve(data);
          })
          .catch(({ response }) => {
            console.error(response)
            reject(response?.data?.error || 'Error al obtener las reservas');
          });
      })
    },
    async exportBookings(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        const token = ApiService.getToken()
        const query = this.filterQuery(filters)
        const url = import.meta.env.VITE_LARAVEL_API_URL + '/api/bookings/export' + (query ? `?${query}` : '')
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
        const params = new URLSearchParams();
        if (!filter || typeof filter !== 'object') return '';
        if (filter.page) params.set('page', String(filter.page));
        if (filter.per_page) params.set('per_page', String(filter.per_page));
        if (filter.status !== undefined && filter.status !== '') params.set('status', String(filter.status));
        if (filter.area_id) params.set('area_id', String(filter.area_id));
        if (filter.department_id) params.set('department_id', String(filter.department_id));
        if (filter.date_from) params.set('date_from', String(filter.date_from));
        if (filter.date_to) params.set('date_to', String(filter.date_to));
        if (filter.amount_type) params.set('amount_type', String(filter.amount_type));
        if (filter.user_id) params.set('user_id', String(filter.user_id));
        if (filter.sort_by) params.set('sort_by', String(filter.sort_by));
        if (filter.sort_dir) params.set('sort_dir', String(filter.sort_dir));
        if (filter.only_residents) params.set('only_residents', '1');
        return params.toString();
      } catch (e) {
        return '';
      }
    }

  },
})
