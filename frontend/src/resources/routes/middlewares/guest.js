import storage from '@/services/storage'
export default function auth(_to, _from, next) {
  document.title = _to.meta.title + ' - Pacifik app'
  const isAuthenticated = storage.getItem('access_token');

  if (!isAuthenticated) {
    next();
  } else {
    next('/dashboard');
  }
};