import { createWebHistory, createRouter } from 'vue-router'

import HomeView from '@/view/homeView.vue'
import authLayout from '@/layouts/authLayout.vue'
import panelLayout from '@/layouts/panelLayout.vue'

import auth from './middlewares/auth'
import guest from './middlewares/guest'
import role from './middlewares/role'

const routes = [
  { 
    path: '/', 
    component: authLayout,
    redirect: '/login',
    beforeEnter: guest,
    children: [
      {
        path:'/login',
        component: () => import('@/view/auth/login.vue'),
        meta:{
          title: 'Bienvenido'
        }
      },
      {
        path:'/register',
        component: () => import('@/view/auth/login.vue'),
        meta:{
          title: 'Bienvenido'
        }
      },
    ]
  },
  { 
    path: '/', 
    component: panelLayout,
    beforeEnter: auth,
    children: [
      {
        path: '/dashboard', 
        component: HomeView, 
        name:'dashboardAdmin',
        beforeEnter: auth,
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Dashboard'
        }
      },
      {
        path: '/admin/users', 
        component: () => import('@/view/admin/usersPage.vue'),
        name:'usersAdmin',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Usuarios',
          roles: ['admin', 'super-admin']
        }
      },
      {
        path: '/admin/finance', 
        component: () => import('@/view/admin/financePage.vue'),
        name:'financePage',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Finanzas'
        }
      },
      // {
      //   path: '/services', 
      //   component: () => import('@/view/admin/servicesPage.vue'),
      //   name:'servicesAdmin',
      //   beforeEnter: [auth, role],
      //   meta:{
      //     title: 'Bienvenido',
      //     pagTitle: 'Sevicios'
      //   }
      // },
      {
        path: '/reserves', 
        component: () => import('@/view/admin/reservesPage.vue'),
        name:'reservedAdmin',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Reservas'
        }
      },
      {
        path: '/balances', 
        component: () => import('@/view/admin/balancesPage.vue'),
        name:'balanceAdmin',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Banlances'
        }
      },
      // {
      //   path: '/config', 
      //   component: () => import('@/view/admin/configPage.vue'),
      //   name:'Configuración',
      //   beforeEnter: [auth, role],
      //   meta:{
      //     title: 'Bienvenido',
      //     pagTitle: 'Dashboard'
      //   }
      // },
      {
        path: '/admin/users/list', 
        component: () => import('@/view/admin/Users/usersList.vue'),
        name:'usersList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Usuarios'
        }
      },
      {
        path: '/admin/department/list', 
        component: () => import('@/view/admin/Department/departmentList.vue'),
        name:'departmentList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Apartamentos'
        }
      },
      {
        path: '/admin/comun-area/list', 
        component: () => import('@/view/admin/ComunAreas/comunAreasList.vue'),
        name:'comunAreasList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Areas comunes'
        }
      },

      {
        path: '/admin/users/form/add', 
        component: () => import('@/view/admin/Users/createUser.vue'),
        name:'usersAdd',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Crear Usuario'
        }
      },
      {
        path: '/admin/users/assing-apartment/:id', 
        component: () => import('@/view/admin/Users/assingApartment.vue'),
        name:'assingDepartment',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Asignar Apartamento'
        }
      },
      {
        path: '/admin/department/form/add', 
        component: () => import('@/view/admin/Department/createDepartment.vue'),
        name:'departmentAdd',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Agregar apartamento'
        }
      },
      {
        path: '/admin/comun-area/form/add', 
        component: () => import('@/view/admin/ComunAreas/createComunArea.vue'),
        name:'comunAreaAdd',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Agregar area común'
        }
      },
      {
        path: '/admin/comun-area/form/update/:id', 
        component: () => import('@/view/admin/ComunAreas/updateComunArea.vue'),
        name:'comunAreaUpdate',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Editar area común'
        }
      },
      {
        path: '/admin/comun-area/bookings/:id/list', 
        component: () => import('@/view/admin/ComunAreas/bookingsList.vue'),
        name:'comunAreaBookingsList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Lista de reservaciones'
        }
      },
      {
        path: '/admin/pay/validate/:id', 
        component: () => import('@/view/admin/Pays/validatePay.vue'),
        name:'PayValidate',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Validar pago'
        }
      },
      {
        path: '/admin/notices', 
        component: () => import('@/view/admin/Notices/noticesPage.vue'),
        name:'noticesPages',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Noticas/Anuncios'
        }
      },
      {
        path: '/admin/events', 
        component: () => import('@/view/admin/Events/eventsPage.vue'),
        name:'eventsPages',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Eventos'
        }
      },
      {
        path: '/admin/events/view/:id',
        component: () => import('@/view/admin/Events/viewEvent.vue'),
        name:'eventViewAdmin',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Detalles de evento'
        }
      },
      {
        path: '/admin/events/form/add', 
        component: () => import('@/view/admin/Events/createEvent.vue'),
        name:'eventsCreate',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Crear evento'
        }
      },
      {
        path: '/admin/events/form/update/:id', 
        component: () => import('@/view/admin/Events/updateEvent.vue'),
        name:'eventsUpdate',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Editar evento'
        }
      },
      

      // ---- client Routes -----

      {
        path: '/client/reserves/list', 
        component: () => import('@/view/client/Reserves/reserveList.vue'),
        name:'reserveClient',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Reservas'
        }
      },
      {
        path: '/client/reserves/form/add', 
        component: () => import('@/view/client/Reserves/createReserve.vue'),
        name:'reserveClientAdd',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Reservas'
        }
      },
      {
        path: '/client/reserves/confirm-reserve/:id', 
        component: () => import('@/view/client/Reserves/confirmReserve.vue'),
        name:'reserveConfirm',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Reservas realizada'
        }
      },
      {
        path: '/client/reserves/pay-reserve/:id', 
        component: () => import('@/view/client/Payments/payForm.vue'),
        name:'reservePay',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Realiza el pago'
        }
      },
      {
        path: '/client/pay/details/:id', 
        component: () => import('@/view/client/Payments/payFinish.vue'),
        name:'payConfirm',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Pago realizado!'
        }
      },
      {
        path: '/client/reserves/view/:id', 
        component: () => import('@/view/client/Reserves/viewReserve.vue'),
        name:'viewReserve',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Detalles de reserva'
        }
      },
      {
        path: '/client/notifications',
        component: () => import('@/view/client/Notifications/notificationsPage.vue'),
        name:'notificationsPage',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Notificaciones'
        }
      },
      {
        path: '/client/pays/list',
        component: () => import('@/view/client/Payments/paymentHistory.vue'),
        name:'paymentHistory',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Historial de pagos'
        }
      },
      {
        path: '/client/department/options',
        component: () => import('@/view/client/Apartments/optionList.vue'),
        name:'apartmentOption',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Gestion de apartamento'
        }
      },
      {
        path: '/client/department/my-unit',
        component: () => import('@/view/client/Apartments/myUnit.vue'),
        name:'apartmentClient',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Gestion de apartamento'
        }
      },
      {
        path: '/client/balance/list',
        component: () => import('@/view/client/Quotas/quotasByUserList.vue'),
        name:'quotaList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Balance de pagos'
        }
      },
      {
        path: '/client/quota/pay/:id', 
        component: () => import('@/view/client/Payments/payForm.vue'),

        name:'quotaPay',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Realiza el pago'
        }
      },
      {
        path: '/client/quota/view/:id', 
        component: () => import('@/view/client/Quotas/viewQuota.vue'),
        name:'viewQuota',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Detalles de cuota'
        }
      },
      {
        path: '/client/notices/list',
        component: () => import('@/view/client/Notices/noticesList.vue'),
        name:'noticeList',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Panel informativo'
        }
      },
      {
        path: '/client/notice/view/:id',
        component: () => import('@/view/client/Notices/noticesView.vue'),
        name:'noticeView',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Información'
        }
      },
      {
        path: '/client/events', 
        component: () => import('@/view/client/Events/eventsPage.vue'),
        name:'eventsClientPages',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Eventos'
        }
      },
      {
        path: '/client/events/view/:id',
        component: () => import('@/view/client/Events/viewEvent.vue'),
        name:'eventClientViewAdmin',
        beforeEnter: [auth, role],
        meta:{
          title: 'Bienvenido',
          pagTitle: 'Detalles de evento'
        }
      },
      
    ]
  },
  // Ruta 404 - debe estar al final para capturar todas las rutas no encontradas
  {
    path: '/:pathMatch(.*)*',
    component: () => import('@/view/errors/404.vue'),
    name: '404',
    meta: {
      title: 'Página no encontrada',
      pagTitle: '404'
    }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router