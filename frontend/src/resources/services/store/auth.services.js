import { defineStore } from 'pinia'
import ApiService from '@/services/axios'
import storage from '@/services/storage'
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: {},
    currency: storage.getItem("currency") || null,
    currencySymbol: storage.getItem("currency_symbol") || null,
  }),
  actions: {
    setAuth({data}){
      this.setUser(data)
      this.setCurrencyConfig(data)
      // this.setIsAdmin(user.data.user)
    },  
    setUser(user){
      this.user = user;
    },
    setCurrencyConfig(authPayload) {
      const {
        currency,
        currencySymbol
      } = authPayload

      this.currency = currency
      this.currencySymbol = currencySymbol

      if (currency) {
        storage.setItem("currency", currency)
      } else {
        storage.deleteItem("currency")
      }

      if (currencySymbol) {
        storage.setItem("currency_symbol", currencySymbol)
      } else {
        storage.deleteItem("currency_symbol")
      }
    },
    setIsAdmin(user){
      // storage.setItem("is_admin",  user.rol_id !== '3' ? true : false);
      // storage.setItem("user_unique_id",user.id);
    },
    setRememberAccount({user, password, remember}){
      storage.setItem("rememberUser", user);
      storage.setItem("rememberPassword", password);
      storage.setItem("isRemember", remember);
    },
    clearRememberAccount(){
      storage.deleteItem("rememberUser");
      storage.deleteItem("rememberPassword");
      storage.deleteItem("isRemember");
    },
    preLogin(credentials){
      this.clearRememberAccount()
      if(credentials.remember == true) this.setRememberAccount(credentials)
    },
    saveToken(token){
      storage.setItem("access_token",token);

    },
    logoutAction(){
      storage.deleteItem("access_token");
      storage.deleteItem("currency");
      storage.deleteItem("currency_symbol");
      this.user = {};
      this.currency = null;
      this.currencySymbol = null;
    },
    async login(credentials) {
      // return await new Promise((resolve, reject) => {
      //   // ApiService.setHeader()
      //   ApiService.get('/sanctum/csrf-cookie')
      //   .then((response) => {
      //     ApiService.post('/api/login', credentials)
      //     .then(({data}) => {
      //       if(!data.data.token){
      //         throw data;
      //       }
      //       this.saveToken(data.data.token)

      //       this.currentUser().then((res) => {
      //         resolve(res)
      //       })
            
      //     }).catch(({response}) =>{
  
      //       reject(response)
      //     })
      //   }).catch(({response}) =>{

      //     reject(response)
      //   })
      // }) 
      return await new Promise((resolve, reject) => {
        // Elimina la llamada a ApiService.get('/sanctum/csrf-cookie')
        ApiService.post('/api/login', credentials)
          .then(({data}) => {
            if(!data.data.token){
              throw data;
            }
            // Aquí guardas el token de Sanctum generado por tu backend
            this.saveToken(data.data.token) 
    
            this.currentUser().then((res) => {
              resolve(res)
            })
          }).catch(({response}) =>{
            reject(response)
          })
      })
      .catch((response) => {
        return response
      })
    },
    async currentUser() {
      return await new Promise((resolve, reject) => {
        if (!ApiService.getToken()) {
          throw '';
        }
          ApiService.setHeader();
          ApiService.get("/api/user")
            .then((data) => {
              if(data.status !=200){
                throw data;
              }
              this.setAuth(data)
              resolve(data);
            }).catch(( response ) => {
              console.log(response)
              reject('Error al obtener usuario');
            });
        
      })
      .catch(( response ) => {
        console.log(response)
        return ('Error al obtener usuario');
      });
    },
    async logout(){
      return await new Promise((resolve) => {
        if (ApiService.getToken()) {
          ApiService.setHeader();
          ApiService.get("/api/auth/logout")
            .then(({ data }) => {
              if(data.code !== 200){
                throw data;
              }
              this.logoutAction()
              resolve(data)
            })
        }
      })
      .catch(( response ) => {
        console.log(response)
        resolve('Error al cerrar sesión');
      });
    },
  },
})