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
        path: '/admin/account-bank',
        component: () => import('@/view/admin/BankAccount/accountBankList.vue'),
        name: 'bankAccountPage',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Finanzas',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/financial-accounts',
        component: () => import('@/view/admin/FinancialAccounts/financialAccountsList.vue'),
        name: 'financialAccountsList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Cuentas financieras',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/financial-accounts/add',
        component: () => import('@/view/admin/FinancialAccounts/createFinancialAccount.vue'),
        name: 'financialAccountsCreate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Nueva cuenta financiera',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/financial-accounts/update/:id',
        component: () => import('@/view/admin/FinancialAccounts/updateFinancialAccount.vue'),
        name: 'financialAccountsUpdate',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar cuenta financiera',
          roles: ['admin', 'super-admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/accounts',
        component: () => import('@/view/admin/accountsPage.vue'),
        name: 'accountsPage',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Cuentas bancarias y financieras',
          roles: ['admin'],
          depth: 2,
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
          pagTitle: 'Balances',
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
          pagTitle: 'Departamentos',
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
        path: '/admin/users/assign-property/:id',
        component: () => import('@/view/admin/Users/assignPropertyPage.vue'),
        name: 'assignProperty',
        meta: {
          pagTitle: 'Gestión de Propiedades',
          title: 'Gestión de Propiedades',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/department/form/add',
        component: () => import('@/view/admin/Department/createUnit.vue'),
        name: 'departmentAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Agregar departamento',
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
        path: '/admin/comun-area/maintenance/:id/create',
        component: () => import('@/view/admin/ComunAreas/createMaintenance.vue'),
        name: 'createMaintenance',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Programar mantenimiento',
          roles: ['admin'],
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
      {
        path: '/admin/apartments/edit/:id',
        component: () => import('@/view/admin/Department/updateDepartment.vue'),
        name: 'updateDepartment',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar inmobiliario',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/department/owner-info/:id',
        component: () => import('@/view/admin/Department/ownerDepartmentInfo.vue'),
        name: 'ownerDepartmentInfo',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Información del propietario',
          roles: ['admin', 'super-admin'],
          depth: 4,
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
        path: '/admin/expenses/list',
        component: () => import('@/view/admin/Expenses/expensesList.vue'),
        name: 'ExpensesList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gastos',
          roles: ['admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/expenses/form/add',
        component: () => import('@/view/admin/Expenses/expenseForm.vue'),
        name: 'ExpenseAdd',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Registrar gasto',
          roles: ['admin'],
          depth: 4,
        },
      },
      {
        path: '/admin/expenses/edit/:id',
        component: () => import('@/view/admin/Expenses/expenseForm.vue'),
        name: 'ExpenseEdit',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Editar gasto',
          roles: ['admin'],
          depth: 4,
        },
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
        path: '/admin/water_readings/view/:id',
        component: () => import('@/view/admin/WaterReadings/waterReadingDetails.vue'),
        name: 'waterReadingDetails',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle medición de agua',
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
      {
        path: '/admin/quotas/pays',
        component: () => import('@/view/admin/Quotas/quotasMenu.vue'),
        name: 'quotasPaysMenu',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Menu de quotas',
          roles: ['admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/quotas/maintenance/list',
        component: () => import('@/view/admin/Quotas/quotasMaintenaceList.vue'),
        name: 'quotasMaintenanceList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Cuotas de mantenimiento',
          roles: ['admin'],
          depth: 1,
        },
      },
      {
        path: '/admin/quotas/maintenance/list/:year/:month',
        component: () => import('@/view/admin/Quotas/quotasMaintenanceMonthDetail.vue'),
        name: 'quotasMaintenanceMonthDetail',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Cuotas del mes',
          roles: ['admin'],
          depth: 2,
        },
      },
      {
        path: '/admin/quota/details/month/:month',
        component: () => import('@/view/client/Quotas/allDetailQuotaByMonth.vue'),
        name: 'quotasDetailByMonthAdmin',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle de cuotas por mes',
          roles: ['admin'],
          depth: 2,
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
          depth: 6,
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
        path: '/client/reserves/extend/:id',
        component: () => import('@/view/client/Reserves/extendReserve.vue'),
        name: 'extendReserve',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Extender reserva',
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
        path: '/client/pays/menu',
        component: () => import('@/view/client/Payments/paymentsMenu.vue'),
        name: 'paymentMenu',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Menu de pagos',
          depth: 3,
        },
      },
      {
        path: '/client/legals/menu',
        component: () => import('@/view/client/Legals/legalsMenu.vue'),
        name: 'legalMenu',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Cumplimiento legal',
          depth: 3,
        },
      },
      {
        path: '/client/claims/add',
        component: () => import('@/view/client/Claims/claimsCreate.vue'),
        name: 'paymentClaims',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Nuevo reclamo',
          depth: 4,
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
          depth: 4,
        },
      },
      {
        path: '/client/department/options',
        component: () => import('@/view/client/Apartments/optionList.vue'),
        name: 'apartmentOption',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Gestion de departamento',
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
          pagTitle: 'Mi departamento',
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
          pagTitle: 'Cuotas de mantenimientos',
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
        path: '/client/quota/details/month/:month',
        component: () => import('@/view/client/Quotas/allDetailQuotaByMonth.vue'),

        name: 'quotaDeailByMonth',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de cuotas',
          depth: 3,
        },
      },
      {
        path: '/client/quota/water-detail/:id',
        component: () => import('@/view/client/Payments/waterDetailClient.vue'),
        name: 'quotaWaterDetailClient',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle de agua',
          depth: 4,
        },
      },
      {
        path: '/client/quota/maintenance-detail/:id',
        component: () => import('@/view/client/Payments/maintenanceDetailClient.vue'),
        name: 'quotaMaintenanceDetailClient',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle de mantenimiento',
          depth: 4,
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
        path: '/client/pay/quotas/view/:id',
        component: () => import('@/view/client/Payments/viewPayOfQuotas.vue'),
        name: 'viewPayOfQuotas',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Pago de cuotas',
          depth: 4,
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
        path: '/client/incidents',
        component: () => import('@/view/client/Incidents/incidentList.vue'),
        name: 'incidentList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Incidencias',
          depth: 2,
        },
      },
      {
        path: '/client/incidents/create',
        component: () => import('@/view/client/Incidents/createIncident.vue'),
        name: 'createIncident',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reportar Incidencia',
          depth: 3,
        },
      },
      {
        path: '/client/incidents/view/:id',
        component: () => import('@/view/client/Incidents/viewIncident.vue'),
        name: 'viewIncident',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalle de Incidencia',
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
        path: '/client/visits/view/:id',
        component: () => import('@/view/client/Visits/viewVisit.vue'),
        name: 'viewVisit',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Detalles de visita',
          depth: 4,
        },
      },

      //----------security----
      {
        path: '/security/airbnb/list',
        component: () => import('@/view/security/Visits/airbnbList.vue'),
        name: 'airbnbsSecurityList',
        beforeEnter: [auth, role],
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
        path: '/security/airbnb/list',
        component: () => import('@/view/security/Visits/airbnbList.vue'),
        name: 'airbnbList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Control de visitas',
          roles: ['trabajador'],
          depth: 2,
        },
      },
      {
        path: '/security/reserves/list',
        component: () => import('@/view/security/Reserves/reservesSecurityList.vue'),
        name: 'reservesSecurityList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Reservas',
          roles: ['trabajador'],
          depth: 2,
        },
      },
      {
        path: '/security/departments/list',
        component: () => import('@/view/security/Departments/departmentsSecurityList.vue'),
        name: 'departmentsSecurityList',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Departamentos',
          roles: ['trabajador'],
          depth: 2,
        },
      },
      {
        path: '/security/department/:id/residents',
        component: () => import('@/view/security/Departments/departmentResidents.vue'),
        name: 'departmentResidents',
        beforeEnter: [auth, role],
        meta: {
          title: 'Bienvenido',
          pagTitle: 'Residentes',
          roles: ['trabajador'],
          depth: 3,
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