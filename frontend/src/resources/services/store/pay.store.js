import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const usePayStore = defineStore('Pay', {
  actions: {
    async getPaysByUser(filters = {}) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        const queryParams = this.formatFiltersQuery(filters) // Construir query string con filtros
        
        const url = queryParams.toString() 
          ? `/api/pays?${queryParams.toString()}`
          : '/api/pays';
        
        ApiService.get(url)
        .then(({data}) => {
          if(data.code !=200) throw data;
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })
    },
    async createPay(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/pays', data)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          if(response.data.code == 403){
            reject(response.data);
          }
          reject(response.data.error);
        });
        
      })

    },
     
    async getPayById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/pays/byId/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
  
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })

    },
     
    async updatePay(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.put('/api/pays/'+data.id, data)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          if(response.data.code == 403){
            reject(response.data);
          }
          reject(response.data.error);
        });
        
      })

    },
    async getAvailablePayInDayByArea(data){
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/pays/availableBooking/'+data.idArea+'?date='+data.date+'&')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
      })

    },
    async deletePay(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.delete('/api/pays/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
      })
    },
    async updateStatus(data) {      
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/pays/updateStatus/'+data.id, data.data)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          if(response.data.code == 403){
            reject(response.data);
          }
          reject(response.data.error);
        });
        
      })

    },
    async validatePayment({ id, data }) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader()
        ApiService.post('/api/pays/validate/' + id, data)
          .then(({ data }) => {
            if (data.code !== 200) throw data
            resolve(data)
          })
          .catch(({ response }) => {
            const body = response?.data
            if (body?.code === 403) {
              reject(body)
              return
            }
            const err = body?.error
            let msg =
              typeof err === 'object' && err !== null && 'message' in err
                ? err.message
                : err
            if (typeof msg !== 'string' || msg === '') {
              msg = 'Error al validar el pago'
            }
            reject(typeof msg === 'string' ? { message: msg, raw: err } : { message: msg, raw: body })
          })
      })
    },
    async createCulqiPay(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw 'No session';
        
        ApiService.setHeader();
        ApiService.post('/api/pays/culqi-payment', data)
          .then(({ data }) => {
            if (data.code !== 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response.data.error || 'Error al procesar pago');
          });
      });
    },
    formatFiltersQuery(filters){
      const queryParams = new URLSearchParams();
      Object.keys(filters).forEach(key => {
        if (filters[key] !== null && filters[key] !== '' && filters[key] !== undefined) {
          queryParams.append(key, filters[key]);
        }
      });

      return queryParams;
    }
    
  },
})
