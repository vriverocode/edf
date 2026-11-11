import { defineStore } from 'pinia'
import ApiService from '@/services/axios'
import storage from '@/services/storage'
import {
  clearBiometricEnrollment,
  deleteStoredBiometricToken,
  disableBiometric,
  hasBiometricEnabled
} from '@/composables/useBiometric'
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: {},
    token: null,
    currency: storage.getItem("currency") || null,
    currencySymbol: storage.getItem("currency_symbol") || null,
  }),
  actions: {
    setAuth({ data }) {
      this.setUser(data)
      this.setCurrencyConfig(data)
      // this.setIsAdmin(user.data.user)
    },
    setUser(user) {
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
    setIsAdmin(user) {
      // storage.setItem("is_admin",  user.rol_id !== '3' ? true : false);
      // storage.setItem("user_unique_id",user.id);
    },
    setRememberAccount({ username, password, isRememberUser }) {
      storage.setItem("rememberUser", username);
      //storage.setItem("rememberPassword", password);
      storage.setItem("isRememberUser", isRememberUser);
    },
    clearRememberAccount() {
      storage.deleteItem("rememberUser");
      //storage.deleteItem("rememberPassword");
      storage.deleteItem("isRememberUser");
    },
    preLogin(credentials) {
      this.clearRememberAccount()
      console.log(credentials)
      if (credentials.isRememberUser == true) this.setRememberAccount(credentials)
    },
    saveToken(token, { persist = true } = {}) {
      this.token = token || null
      ApiService.setToken(this.token)

      if (persist) {
        storage.setItem("access_token", token);
      } else {
        storage.deleteItem("access_token");
      }
    },
    logoutAction() {
      storage.deleteItem("access_token");
      storage.deleteItem("currency");
      storage.deleteItem("currency_symbol");
      ApiService.clearToken();
      this.token = null;
      this.user = {};
      this.currency = null;
      this.currencySymbol = null;
    },
    async login(credentials, { persistToken = true } = {}) {
      try {
        this.preLogin(credentials)
        const { data } = await ApiService.post('/api/login', credentials)
        if (!data?.data?.token) {
          throw data
        }
        this.saveToken(data.data.token, { persist: persistToken })
        return await this.currentUser()
      } catch (error) {
        return error?.response ?? error
      }
    },
    async setSession(token) {
      if (!token) {
        throw new Error('Token de sesión no válido')
      }

      this.saveToken(token, { persist: false })

      try {
        const response = await this.currentUser()
        if (!this.user?.id) {
          throw new Error('No se pudo recuperar el usuario')
        }
        return response
      } catch (error) {
        this.logoutAction()

        if (error?.response?.status === 401 || error?.status === 401) {
          await deleteStoredBiometricToken()
          clearBiometricEnrollment()
        }

        throw error
      }
    },
    async currentUser() {
      if (!ApiService.getToken()) {
        throw new Error('No hay una sesión activa')
      }

      ApiService.setHeader()
      const response = await ApiService.get('/api/user')

      if (response.status !== 200) {
        throw response
      }

      this.setAuth(response)
      return response
    },
    async logout() {
      if (hasBiometricEnabled()) {
        this.logoutAction()
        return { code: 200, message: 'Sesión cerrada' }
      }

      try {
        if (ApiService.getToken()) {
          ApiService.setHeader();
          const { data } = await ApiService.get("/api/auth/logout")

          if (data.code !== 200) {
            throw data;
          }
        }
      } catch (error) {
        console.error(error)
      } finally {
        await deleteStoredBiometricToken()
        disableBiometric()
        this.logoutAction()
      }

      return { code: 200, message: 'Sesión cerrada' }
    },
    async updateProfile(data) {
      return await new Promise((resolve, reject) => {
        ApiService.setHeader()
        ApiService.put('/api/profile', data)
          .then(async ({ data }) => {
            if (data.code !== 200) {
              throw data
            }
            await this.currentUser()
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response)
          })
      })
    },
    async forgotPassword(email) {
      return await new Promise((resolve, reject) => {
        ApiService.post('/api/forgot-password', { email })
          .then(({ data }) => {
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response)
          })
      })
    },
    async validateResetToken(token) {
      return await new Promise((resolve, reject) => {
        ApiService.post('/api/validate-reset-token', { token })
          .then(({ data }) => {
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response)
          })
      })
    },
    async resetPassword(payload) {
      return await new Promise((resolve, reject) => {
        ApiService.post('/api/reset-password', payload)
          .then(({ data }) => {
            resolve(data)
          })
          .catch(({ response }) => {
            reject(response)
          })
      })
    },
  },
})