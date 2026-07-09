import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useNoticeStore = defineStore('Notices', {
  state: () => ({
    group: [
      { name: "Emprendimiento", value: 1 },
      { name: "Producto", value: 2 },
      { name: "Servicio", value: 3 },
      { name: "Empleo", value: 5 },
      { name: "Vehículo", value: 4 },
      { name: "Inmuebles", value: 6 },
      { name: "Oportunidades", value: 7 },
      { name: "Otros", value: 8 },
    ],
    category: [
      [],
      [
        { name: "🍰 Alimentos y postres", value: 0 },
        { name: "👕 Moda y accesorios", value: 1 },
        { name: "💄 Belleza", value: 2 },
        { name: "🏠 Hogar", value: 3 },
        { name: "🎁 Regalos", value: 4 },
        { name: "🧵 Manualidades", value: 5 },
      ],
      [
        { name: "Compra y venta", value: 0 },
        { name: "Venta de artículos", value: 1 },
        { name: "Compra de artículos", value: 2 },
        { name: "Alquiler", value: 3 },
        { name: "Vehículos", value: 4 },
        { name: "Inmuebles", value: 5 },
      ],
      [
        { name: "👨‍🔧 Servicios técnicos", value: 0 },
        { name: "🧹 Limpieza", value: 1 },
        { name: "👩‍🏫 Clases particulares", value: 2 },
        { name: "🐶 Mascotas", value: 3 },
        { name: "💻 Tecnología", value: 4 },
        { name: "🚗 Transporte", value: 5 },
      ],
      [
        { name: "Oferta laboral", value: 0 },
        { name: "Servicios", value: 1 },
      ],
      [
        { name: "Venta de automovil", value: 0 },
        { name: "Venta de maquinaria", value: 1 },
        { name: "Venta de respuesto", value: 2 },
        { name: "Compra de automovil", value: 3 },
        { name: "Compra de maquinaria", value: 4 },
        { name: "Compra de respuesto", value: 5 },
        { name: "Servicios", value: 6 },
      ],
      [
        { name: "Venta de inmueble", value: 0 },
        { name: "Alquier de inmueble", value: 1 },
        { name: "Servicios", value: 2 },
      ],
      [
        { name: "Empleos", value: 0 },
        { name: "Eventos", value: 1 },
        { name: "Promociones", value: 2 },
        { name: "Otros", value: 3 },
      ],
      [
        { name: "Información junta de condominio", value: 0 },
      ]
    ]
  }),
  actions: {
    async getNotices(filters) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }

        ApiService.setHeader();
        const query = this.filterQuery(filters);
        const url = '/api/notices' + (query ? `?${query}` : '');

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
    async createNotice(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/notices', data)
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
    async getNoticeById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/notices/byId/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });

      })
    },
    async updateNotice(data, id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/notices/' + id, data)
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
    async deleteNotices(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.delete('/api/notices/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async getPendingNotices() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/notices/pendings')
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async setViewer(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/notices/set-viewer/' + id)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async setNewStatus(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/notices/set-new-status/' + data.id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data;

            resolve(data);
          }).catch(({ response }) => {
            console.log(response)
            reject(response.data.error);
          });
      })
    },
    async getUserWithPublish() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw 'no-data'
        }
        ApiService.setHeader()
        ApiService.get('/api/users/with-publish')
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
        if (filter.group && filter.group !== -1) params.set('group', String(filter.group));
        if (filter.category && filter.category !== -1) params.set('category', String(filter.category));
        if (filter.post_by && filter.post_by !== -1) params.set('post_by', String(filter.post_by));
        if (filter.date_from) params.set('date_from', String(filter.date_from));
        if (filter.date_to) params.set('date_to', String(filter.date_to));
        if (filter.only_my_posts) params.set('only_my_posts', String(filter.only_my_posts));

        return params.toString() + '&';
      } catch (e) {
        return '';
      }
    },
    getCategoryByGroup(idGroup) {
      return this.category[idGroup]
    }

  },
})
