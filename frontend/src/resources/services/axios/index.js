import axios from "axios";
import storage from '@/services/storage'
/**
 * Service to call HTTP request via Axios
 */

axios.defaults.withCredentials = true
axios.defaults.withXSRFToken = true
axios.defaults.baseURL = 'https://website-c67adca2.sfr.hrf.mybluehost.me'

const ApiService = {
  
  /**
   * Set the default HTTP request headers
   */
  getToken(){
    return storage.getItem('access_token')
  },
  
  setHeader() {
    
    axios.defaults.headers.common[
      "Authorization"
    ] = `Bearer ${this.getToken()}`;
    axios.defaults.headers.common[
      "Accept"
    ] = `application/json`;

  },

  query(resource, params) {
    return axios.get(resource, params);
  },

  /**
   * Send the GET HTTP request
   * @param resource
   * @param slug
   * @returns {*}
   */
  get(resource, slug = "") {
    return axios.get(`${resource}${slug}`);
  },

  /**
   * Set the POST HTTP request
   * @param resource
   * @param params
   * @returns {*}
   */
  post(resource, params) {
    return axios.post(`${resource}`, params);
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
   * @returns {*}
   */
  delete(resource) {
    return axios.delete(resource);
  }
};

export default ApiService;
