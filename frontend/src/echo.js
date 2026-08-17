import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;
const token = localStorage.getItem('access_token');

window.Echo = new Echo({
  broadcaster: 'pusher',
  key: import.meta.env.VITE_PUSHER_APP_KEY,
  cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
  forceTLS: true,
  authEndpoint: import.meta.env.VITE_LARAVEL_API_URL + '/broadcasting/auth',
  // authEndpoint: 'http://192.168.31.117:8030/broadcasting/auth',
  // authEndpoint: 'http://192.168.1.161:8030/broadcasting/auth',


  auth: {
    headers: {
      Authorization: token ? `Bearer ${token}` : '',
      Accept: 'application/json',
    },
    withCredentials: true,
  },
})

