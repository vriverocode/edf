import ApiService from '@/services/axios'
export default function auth(_to, _from, next) {
  document.title = _to.meta.title + ' - Pacifik app'
  const isAuthenticated = ApiService.getToken();

  if (isAuthenticated) {
    next();
  } else {
    next('/login');
  }
};