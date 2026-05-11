import Echo from 'laravel-echo';
 
import Pusher from 'pusher-js';
window.Pusher = Pusher;
const token = localStorage.getItem('access_token'); 

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  authEndpoint: 'https://website-a40e47dc.gtq.fvz.mybluehost.me/broadcasting/auth',
  // authEndpoint: 'http://192.168.31.20:8030/broadcasting/auth',
  // authEndpoint: 'http://192.168.1.183:8030/broadcasting/auth',

  auth: {
    headers: {
      Authorization: token ? `Bearer ${token}` : '',
      Accept: 'application/json',
    },
    withCredentials: true,
  },
})

