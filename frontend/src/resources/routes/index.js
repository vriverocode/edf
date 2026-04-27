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
        path: '/login',
        component: () => import('@/view/auth/login.vue'),
        meta: {
          title: 'Bienvenido',
          depth: -1,
        },
      },
      {
        path: '/register',
        component: () => import('@/view/auth/login.vue'),
        meta: {
          title: 'Bienvenido',
        },
      },
    ],
  },
  {
    path: '/',
    component: panelLayout,
    beforeEnter: auth,
    children: [
      {
        path: '/dashboard',
        component: HomeView,
        name: 'dashboardAdmin',
        beforeEnter: auth,
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Dashboard',
          depth: 0,
          roles: ['admin', 'super-admin', 'propietario'],
        },
      },
      {
        path: '/admin/users',
        component: () => import('@/view/admin/usersPage.vue'),
        name: 'usersAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Usuarios',
          roles: ['admin', 'super-admin'],
          depth: 1,
        },
      },
      {
        path: '/admin/finance',
        component: () => import('@/view/admin/financePage.vue'),
        name: 'financePage',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Finanzas',
          roles: ['admin', 'super-admin', 'propietario'],
          depth: 1,
        },
      },
      {
        path: '/admin/account-data',
        component: () => import('@/view/admin/BankAccount/accountBankList.vue'),
        name: 'bankAccountPage',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Finanzas',
          roles: ['admin', 'super-admin'],
          depth: 1,
        },
      },
      {
        path: '/admin/account-data/add',
        component: () => import('@/view/admin/BankAccount/createAccountBank.vue'),
        name: 'bankAccountPageAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Finanzas',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/account-data/update/:id',
        component: () => import('@/view/admin/BankAccount/updateAccountBank.vue'),
        name: 'bankAccountPageUpdate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Finanzas',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
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
        name: 'reservedAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reservas',
          depth: 2,
          roles: ['admin', 'super-admin'],
        },
      },
      {
        path: '/balances',
        component: () => import('@/view/admin/balancesPage.vue'),
        name: 'balanceAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Banlances',
          depth: 2,
        },
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
        name: 'usersList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Usuarios',
          depth: 2,
        },
      },
      {
        path: '/admin/department/list',
        component: () => import('@/view/admin/Department/departmentList.vue'),
        name: 'departmentList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Apartamentos',
          depth: 2,
        },
      },
      {
        path: '/admin/comun-area/list',
        component: () => import('@/view/admin/ComunAreas/comunAreasList.vue'),
        name: 'comunAreasList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Areas comunes',
          depth: 2,
        },
      },

      {
        path: '/admin/users/form/add',
        component: () => import('@/view/admin/Users/createUser.vue'),
        name: 'usersAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Crear Usuario',
          depth: 3,
        },
      },
      {
        path: '/admin/users/assing-apartment/:id',
        component: () => import('@/view/admin/Users/assingApartment.vue'),
        name: 'assingDepartment',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Asignar Apartamento',
          depth: 3,
        },
      },
      {
        path: '/admin/department/form/add',
        component: () => import('@/view/admin/Department/createDepartment.vue'),
        name: 'departmentAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Agregar apartamento',
          depth: 3,
        },
      },
      {
        path: '/admin/comun-area/form/add',
        component: () => import('@/view/admin/ComunAreas/createComunArea.vue'),
        name: 'comunAreaAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Agregar area común',
          depth: 3,
        },
      },
      {
        path: '/admin/comun-area/form/update/:id',
        component: () => import('@/view/admin/ComunAreas/updateComunArea.vue'),
        name: 'comunAreaUpdate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar area común',
          depth: 3,
        },
      },
      {
        path: '/admin/comun-area/bookings/:id/list',
        component: () => import('@/view/admin/ComunAreas/bookingsList.vue'),
        name: 'comunAreaBookingsList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Lista de reservaciones',
          depth: 3,
        },
      },
      {
        path: '/admin/pay/validate/:id',
        component: () => import('@/view/admin/Pays/validatePay.vue'),
        name: 'PayValidate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Validar pago',
          depth: 3,
        },
      },
      {
        path: '/admin/notices',
        component: () => import('@/view/admin/Notices/noticesPage.vue'),
        name: 'noticesPages',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Noticas/Anuncios',
          depth: 2,
        },
      },
      {
        path: '/admin/events',
        component: () => import('@/view/admin/Events/eventsPage.vue'),
        name: 'eventsPages',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Eventos',
          depth: 2,
        },
      },
      {
        path: '/admin/events/view/:id',
        component: () => import('@/view/admin/Events/viewEvent.vue'),
        name: 'eventViewAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de evento',
          depth: 2,
        },
      },
      {
        path: '/admin/events/form/add',
        component: () => import('@/view/admin/Events/createEvent.vue'),
        name: 'eventsCreate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Crear evento',
          depth: 3,
        },
      },
      {
        path: '/admin/events/form/update/:id',
        component: () => import('@/view/admin/Events/updateEvent.vue'),
        name: 'eventsUpdate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar evento',
          depth: 3,
        },
      },

      // ---- client Routes -----

      {
        path: '/client/reserves/list',
        component: () => import('@/view/client/Reserves/reserveList.vue'),
        name: 'reserveClient',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reservas',
          depth: 2,
        },
      },
      {
        path: '/client/reserves/form/add',
        component: () => import('@/view/client/Reserves/createReserve.vue'),
        name: 'reserveClientAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reservas',
          depth: 3,
        },
      },
      {
        path: '/client/reserves/confirm-reserve/:id',
        component: () => import('@/view/client/Reserves/confirmReserve.vue'),
        name: 'reserveConfirm',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reservas realizada',
          depth: 3,
        },
      },
      {
        path: '/client/reserves/pay-reserve/:id',
        component: () => import('@/view/client/Payments/payForm.vue'),
        name: 'reservePay',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Realiza el pago',
          depth: 3,
        },
      },
      {
        path: '/client/pay/details/:id',
        component: () => import('@/view/client/Payments/payFinish.vue'),
        name: 'payConfirm',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Pago realizado!',
          depth: 3,
        },
      },
      {
        path: '/client/reserves/view/:id',
        component: () => import('@/view/client/Reserves/viewReserve.vue'),
        name: 'viewReserve',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de reserva',
          depth: 3,
        },
      },
      {
        path: '/client/notifications',
        component: () => import('@/view/client/Notifications/notificationsPage.vue'),
        name: 'notificationsPage',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Notificaciones',
          depth: 2,
        },
      },
      {
        path: '/client/pays/list',
        component: () => import('@/view/client/Payments/paymentHistory.vue'),
        name: 'paymentHistory',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Historial de pagos',
          depth: 3,
        },
      },
      {
        path: '/client/department/options',
        component: () => import('@/view/client/Apartments/optionList.vue'),
        name: 'apartmentOption',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gestion de apartamento',
          depth: 2,
        },
      },
      {
        path: '/client/department/my-unit',
        component: () => import('@/view/client/Apartments/myUnit.vue'),
        name: 'apartmentClient',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gestion de apartamento',
          depth: 3,
        },
      },
      {
        path: '/client/balance/list',
        component: () => import('@/view/client/Quotas/quotasByUserList.vue'),
        name: 'quotaList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Balance de pagos',
          depth: 3,
        },
      },
      {
        path: '/client/quota/pay/:id',
        component: () => import('@/view/client/Payments/payForm.vue'),

        name: 'quotaPay',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Realiza el pago',
          depth: 3,
        },
      },
      {
        path: '/client/quota/view/:id',
        component: () => import('@/view/client/Quotas/viewQuota.vue'),
        name: 'viewQuota',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de cuota',
          depth: 3,
        },
      },
      {
        path: '/client/notices/list',
        component: () => import('@/view/client/Notices/noticesList.vue'),
        name: 'noticeList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Panel informativo',
          depth: 2,
        },
      },
      {
        path: '/client/notice/view/:id',
        component: () => import('@/view/client/Notices/noticesView.vue'),
        name: 'noticeView',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Información',
          depth: 3,
        },
      },
      {
        path: '/client/events',
        component: () => import('@/view/client/Events/eventsPage.vue'),
        name: 'eventsClientPages',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Eventos',
          depth: 2,
        },
      },
      {
        path: '/client/events/view/:id',
        component: () => import('@/view/client/Events/viewEvent.vue'),
        name: 'eventClientViewAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de evento',
          depth: 3,
        },
      },
      {
        path: '/client/familiar/list',
        component: () => import('@/view/client/Familiar/familiarList.vue'),
        name: 'familiarList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Residente / Airbnb',
          depth: 3,
        },
      },
      {
        path: '/client/familiar/list',
        component: () => import('@/view/client/Familiar/familiarList.vue'),
        name: 'familiarList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Residente / Airbnb',
          depth: 3,
        },
      },
      {
        path: '/client/familiar/add',
        component: () => import('@/view/client/Familiar/createFamiliar.vue'),
        name: 'familiarAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Crear residente',
          depth: 4,
        },
      },
      {
        path: '/client/visit/list',
        component: () => import('@/view/client/Visits/visitsList.vue'),
        name: 'visitsList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gestión de visitas',
          depth: 3,
        },
      },
      {
        path: '/client/visit/add',
        component: () => import('@/view/client/Visits/createVisit.vue'),
        name: 'visitAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Registrar visita',
          depth: 4,
        },
      },
      {
        path: '/security/airbnb/list',
        component: () => import('@/view/security/Visits/airbnbList.vue'),
        name: 'airbnbsSecurityList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Control de visitas',
          roles: ['trabajador'],
          depth: 3,
        },
      },
      {
        path: '/security/visit/list',
        component: () => import('@/view/security/Visits/visitsSecurityList.vue'),
        name: 'visitsSecurityList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Control de visitas',
          roles: ['trabajador'],
          depth: 2,
        },
      },
      {
        path: '/admin/monthly_bills/menu',
        component: () => import('@/view/admin/MonthlyBills/monthlyBillsMenu.vue'),
        name: 'MonthlyBillsMenu',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gastos mensuales',
          roles: ['admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/monthly_bills/list',
        component: () => import('@/view/admin/MonthlyBills/monthlyBillsList.vue'),
        name: 'MonthlyBillsList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gastos mensuales',
          roles: ['admin'],
          depth: 3,
        },
      },
      {
        path: '/admin/monthly_bills/form/add',
        component: () => import('@/view/admin/MonthlyBills/monthlyBillsForm.vue'),
        name: 'mothlyBillsAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Agregar gastos mensuales',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/monthly_bills/view/:id',
        component: () => import('@/view/admin/MonthlyBills/monthlyBillsDetails.vue'),
        name: 'monthlyBillsDetails',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle gasto mensual',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/monthly_bills/edit/:id',
        component: () => import('@/view/admin/MonthlyBills/monthlyBillsEditForm.vue'),
        name: 'monthlyBillsEdit',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar gasto mensual',
          roles: ['admin'],
          depth: 5,
        },
      },
      {
        path: '/admin/water_readings/list',
        component: () => import('@/view/admin/WaterReadings/waterReadingsList.vue'),
        name: 'waterReadingsList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Medición de agua',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/monthly_bills/water_read',
        redirect: '/admin/water_readings/list',
      },
      {
        path: '/admin/water_readings/form/add',
        component: () => import('@/view/admin/WaterReadings/waterReadingForm.vue'),
        name: 'waterReadingsAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Registrar medición',
          roles: ['admin'],
          depth: 5,
        },
      },
      {
        path: '/admin/water_readings/edit/:id',
        component: () => import('@/view/admin/WaterReadings/waterReadingEditForm.vue'),
        name: 'waterReadingsEdit',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar medición',
          roles: ['admin'],
          depth: 5,
        },
      },
    ],
  },
  // Ruta 404 - debe estar al final para capturar todas las rutas no encontradas
  {
    path: '/:pathMatch(.*)*',
    component: () => import('@/view/errors/404.vue'),
    name: '404',
    meta: {
      title: 'Página no encontrada',
      pagTitle: '404',
      depth: 5,
    },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router