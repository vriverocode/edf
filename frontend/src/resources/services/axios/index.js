import axios from "axios";
import storage from '@/services/storage'
/**
 * Service to call HTTP request via Axios
 */

axios.defaults.withCredentials = true
// axios.defaults.withXSRFToken = true
axios.defaults.baseURL = import.meta.env.VITE_LARAVEL_API_URL

const ApiService = {

  /**
   * Set the default HTTP request headers
   */
  getToken() {
    return storage.getItem('access_token')
  },

  setHeader() {

    axios.defaults.headers.common["Authorization"] = `Bearer ${this.getToken()}`;
    axios.defaults.headers.common["Accept"] = `application/json`;

  },

  query(resource, params) {
    return axios.get(resource, params);
  },

  /**
   * Send the GET HTTP request
   * @param resource
   * @param slug
   * @param config optional axios config (e.g. { timeout: 10000 })
   * @returns {*}
   */
  get(resource, slug = "", config = {}) {
    return axios.get(`${resource}${slug}`, config);
  },

  /**
   * Set the POST HTTP request
   * @param resource
   * @param params
   * @param config optional axios config (e.g. { timeout: 10000 })
   * @returns {*}
   */
  post(resource, params, config = {}) {
    return axios.post(`${resource}`, params, config);
  },

  /**
   * Send the UPDATE HTTP request
   * @param resource
   * @param slug
   * @param params
   * @returns {IDBRequest<IDBValidKey> | Promise<void>}
   */
  update(resource, slug, params) {
    return axios.put(`${resource}`, params);
  },

  /**
   * Send the PUT HTTP request
   * @param resource
   * @param params
   * @returns {IDBRequest<IDBValidKey> | Promise<void>}
   */
  put(resource, params) {
    return axios.put(`${resource}`, params);
  },

  /**
   * Send the DELETE HTTP request
   * @param resource
   * @param config optional axios config (e.g. { data: { ... } })
   * @returns {*}
   */
  delete(resource, config = {}) {
    return axios.delete(resource, config);
  }
};

export default ApiService;
