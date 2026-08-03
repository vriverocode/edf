import { defineStore } from 'pinia'
import ApiService from '@/services/axios'

export const useComunAreaStore = defineStore('ComunArea', {
  actions: {
    async createComunArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.post('/api/comun-area', data)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          if(response.data.code == 403){
            reject(response.data);
          }
          reject(response.data.error);
        });
        
      })

    },
    async updateComunArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.post('/api/comun-area/u/'+data.id, data)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          if(response.data.code == 403){
            reject(response.data);
          }
          reject(response.data.error);
        });
        
      })

    },
    
    async getComunAreaById(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.get('/api/comun-area/byId/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
  
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })

    },
    async getPaginationComunArea(data) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/comun-area?page='+data.page+'&'+'search='+data.search+'&')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })
    },
    async getComunAreasByFind(find) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/comun-area/byFind?find='+find+'&')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })
    },
    async deleteComunArea(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/comun-area/d/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })

    },
    async toggleComunAreaStatus(id) {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.post('/api/comun-area/toggle-status/'+id)
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })

    },
    async getAllComunAreas(){
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
        ApiService.setHeader();
        ApiService.get('/api/comun-area/all')
        .then(({data}) => {
          if(data.code !=200) throw data;
          
          resolve(data);
        }).catch(( {response}) => {
          console.error(response)
          reject(response.data.error);
        });
        
      })
    }
    
  },
})