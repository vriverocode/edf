import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useApartmentStore = defineStore('Apartment', {
  actions: {
    async createApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.post('/api/apartments', data)
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
    
    async getApartmentById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.get('/api/apartments/byId/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
  
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })

    },
    async getApartmentByUser() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.get('/api/apartments/byUser')
        .then(({data}) => {
          if(data.code !=200) throw data;
  
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })

    },
    async getPaginationApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/apartments?page='+data.page+'&'+'search='+data.search+'&searchType='+data.filter+'&')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })
    },
    async getApartmentsByFind(find) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/apartments/byFind?find='+find+'&')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })
    },
    async deleteApartment(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/users/d/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.log(response)
          reject(response.data.error);
        });
        
      })

    },
    async updateApartment(id, data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw '';
        ApiService.setHeader();
        ApiService.post('/api/apartments/u/' + id, data)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response.data.error);
          });
      });
    },
    async getOwnersWithoutApartment() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw '';
        ApiService.setHeader();
        ApiService.get('/api/users/without-apartment')
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response.data.error);
          });
      });
    },
    async assignApartment(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) throw '';
        ApiService.setHeader();
        ApiService.post('/api/users/assing_apartmet', data)
          .then(({ data }) => {
            if (data.code != 200) throw data;
            resolve(data);
          })
          .catch(({ response }) => {
            reject(response.data.error);
          });
      });
    },
    
  },
})