# AGENTS.md — EDF App (PACIFIK)

## Project structure

Two independent applications in one repo:

- **Root (`/`)** — Laravel 11 REST API backend (PHP 8.2+, MySQL)
- **`frontend/`** — Vue 3 SPA (Vite, Pinia, Quasar + Vant + Tailwind), bundled as Android app via Capacitor

The frontend is fully decoupled from the backend. It communicates via REST API configured through `frontend/.env`.

## Dev commands

**Run everything (backend + queue + Vite):**
```sh
composer dev        # runs: artisan serve + artisan queue:listen + npm run dev (via concurrently)
```

**Backend only:**
```sh
php artisan serve --port 8030
php artisan queue:listen --tries=1
```

**Frontend only (from `frontend/`):**
```sh
npm run dev         # Vite on port 8031, host from VITE_SERVER_IP
npm run dev-home    # alternative host for home network
```

**Tests (Pest PHP):**
```sh
php artisan test    # or: ./vendor/bin/pest
```

**Frontend build (for Capacitor):**
```sh
cd frontend && npm run build   # outputs to frontend/dist/
```

**Format PHP:**
```sh
./vendor/bin/pint
```

## Architecture

### Backend (`app/Http/Controllers/Api/`)
- 32 API controllers, all under `auth:sanctum` middleware
- Routes defined in `routes/api.php` (539 lines) — all prefixed with `/api/`
- Roles: `admin` (1), `super-admin` (5), `propietario` (2), `trabajador` (3) — defined as constants in `app/Models/Rol.php`
- Role enforcement via `EnsureUserHasRole` middleware (`role:admin,super-admin`) and `EnsureRoleIsNot` (`roleIsNot:trabajador`)
- Rate limiting: `throttle:write` on mutation endpoints
- Auth: Sanctum tokens + Fortify (login, registration)
- Real-time: Pusher via Laravel Broadcasting (`config/broadcasting.php`)
- Push notifications: Firebase Cloud Messaging (`kreait/firebase-php`)
- Online payments: Culqi (`CULQI_SECRET_KEY`)
- PDF generation: `barryvdh/laravel-dompdf`
- Excel exports: `maatwebsite/excel`
- Image manipulation: `intervention/image`
- 38 Eloquent models in `app/Models/`

### Models (`app/Models/`) — 38 files

| Model | Key relationships / notes |
|---|---|
| `User` | hasMany: Booking, Pay, Familiar, Guest, Apartament, Notice, Multa. Uses SoftDeletes, HasApiTokens, Notifiable. Appends: `moroso` |
| `Apartament` / `Departament` | belongsTo User (owner). hasMany: Quota, PeoplesXDepartment, WaterReading, DepartmentCharge. Appends: `active_tenant`, `owner` |
| `Booking` | belongsTo: User, Departament, ComunArea. hasOne Pay. hasMany BookingInvitee |
| `BookingInvitee` | belongsTo Booking |
| `ComunArea` | Schedule/rules/type fields. Appends: `type_label`, `status_label` |
| `Quota` | belongsTo: Departament, PeoplesXDepartment. belongsToMany Pay (pivot `pay_quota`). hasOne WaterReading. Static: `resolveCreditPeriods`. Scope: `filter` |
| `Pay` | belongsTo: User, Booking, PayMethod. belongsToMany Quota. hasOne Transaction (`financialTransaction`). Casts: `consolidated_ids` (array) |
| `PayMethod` | Has `commission_percentage` |
| `Notice` | belongsTo User. Constants: `GROUPS`, `CATEGORIES`, `STATUS_LABELS`, `STATUS_COLORS` |
| `Expense` | belongsTo: Provider, ServiceCategory, MonthlyBills, Pay. Scope: `filter` |
| `MonthlyBills` | hasMany Expense |
| `Provider` | belongsTo ServiceCategory. hasMany Expense |
| `ServiceCategory` | hasMany Provider |
| `WaterReading` | belongsTo Departament. Accessor: `consumption` |
| `Multa` | belongsTo: MultaCategory, User, Apartament |
| `MultaCategory` | hasMany Multa |
| `FinancialAccount` | hasMany Transaction |
| `Transaction` | belongsTo: Pay, FinancialAccount, TransactionCategory |
| `TransactionCategory` | hasMany Transaction. Type: 1=income, 2=expense |
| `CreditTransaction` | belongsTo: Quota, Pay. morphTo creditable |
| `Refund` | belongsTo: Pay, Booking, User |
| `AnnualBudget` | hasMany BudgetLine |
| `BudgetLine` | belongsTo AnnualBudget |
| `DepartmentCharge` | belongsTo: Departament, Expense |
| `PeoplesXDepartment` | Pivot-like for tenant assignments. belongsTo: User, Departament |
| `Familiar` | belongsTo User |
| `Guest` | belongsTo: User, Apartament |
| `Visit` | Visitor tracking |
| `SecurityCode` | Security verification |
| `Rol` | Constants: ADMIN=1, SUPER_ADMIN=5, PROPIETARIO=2, TRABAJADOR=3 |

### Services (`app/Services/`)
- `MonthlyQuotaService.php` — generación de cuotas mensuales (calcula gastos/m², agua, cargos especiales, filtro por año)
- `QuotaEligibilityService.php` — validación de elegibilidad de cuotas para pago (orden cronológico, no repetidas, permisos de inquilino)
- `BillInvoiceService.php` — genera PDF de factura mensual con desglose, envía por mail
- `BookingPendingPayNotifier.php` — envía recordatorios de pago pendiente para reservas
- `RefundService.php` — procesa reembolsos de reservas canceladas

### Exports (`app/Exports/`)
- `MonthlyPaymentsExport.php` — exportación de pagos mensuales (maatwebsite/excel)
- `PaymentsExport.php` — exportación general de pagos
- `BookingsExport.php` — exportación de reservas
- `DelinquentsExport.php` — exportación de morosos

### Mail (`app/Mail/`)
- `BillInvoiceMail.php` — envío de facturas PDF adjuntas
- `PayClaims.php` — reclamaciones de pago
- `ResetPasswordMail.php` — reset de contraseña

### Console Commands (`app/Console/Commands/`) — 11 archivos

| Comando | Signature | Descripción |
|---|---|---|
| `MonthlyQuota.php` | `app:monthly-quota` | Generación mensual de cuotas (cron) |
| `BookingPendingPaymentReminders.php` | `app:booking-pending-pay-reminders` | Recordatorios de pago de reservas |
| `CheckUserMorosos.php` | `app:check-user-morosos` | Verificar y marcar morosos |
| `AutoCompleteBookings.php` | `app:auto-complete-bookings` | Autocompletar reservas pasadas |
| `AutocompletarMantenimiento.php` | `app:autocompletar-mantenimiento` | Autocompletar mantenimientos finalizados |
| `ActiveOrdesactiveAirBnbUsers.php` | `app:active-ordesactive-air-bnb-users` | Activar/desactivar usuarios Airbnb |
| `UpdateEventStatuses.php` | `app:update-event-statuses` | Actualizar estados de eventos (próximamente, en curso, completada) |
| `CleanOldNotifications.php` | `app:clean-old-notifications` | Limpieza de notificaciones antiguas |
| `BackfillMaintenancesSeries.php` | `backfill:maintenances-series` | Asignar series_id a mantenimientos multidía |
| `BackfillQuotasTenantPivot.php` | `backfill:quotas-tenant-pivot` | Asignar inquilino a cuotas pendientes |
| `updateYearInQuota.php` | `app:update-year-in-quota` | Migración/backfill: agregar columna `year` a quotas |

### Scheduled Tasks (`routes/console.php`)
```php
Schedule::command('app:auto-complete-bookings')->everyMinute();
Schedule::command('app:booking-pending-payment-reminders')->dailyAt('15:00');
Schedule::command('app:active-ordesactive-air-bnb-users')->everyMinute();
```

### Middleware custom (`app/Http/Middleware/`)
- `EnsureUserHasRole.php` — validación de roles (usage: `role:admin,super-admin`)
- `EnsureRoleIsNot.php` — exclusión de roles (usage: `roleIsNot:trabajador`)
- `SecurityHeaders.php` — headers de seguridad (X-Frame-Options, etc.)
- `Authenticate.php` — fix para API sin auth (retorna `null` en `redirectTo()`)

### API Route Groups (`routes/api.php`)
- `/api/user` — auth, profile, dashboard
- `/api/users` — admin user management
- `/api/departaments` — department CRUD
- `/api/quotas` — quota management
- `/api/pays` — payment CRUD + approval + Culqi online payments
- `/api/bookings` — reservation management
- `/api/comun-areas` — common area management
- `/api/notices` — news/announcements
- `/api/multas` — fines management
- `/api/expenses` — expense management
- `/api/monthly-bills` — monthly bills
- `/api/providers` — provider management
- `/api/water-readings` — water readings
- `/api/reports` — various reports (Excel exports)
- `/api/financial-accounts` — financial accounts
- `/api/credits` — credit/overpayment management
- `/api/annual-budget` — annual budget
- `/api/department-charges` — department charges
- `/api/familiars` — family members
- `/api/guests` — guest management
- `/api/visits` — visit tracking
- `/api/maintenance` — maintenance scheduling

### Frontend (`frontend/src/resources/`)
- `@` alias resolves to `frontend/src/resources/`
- Vue Router with role-based guards: `middlewares/auth.js`, `middlewares/role.js`, `middlewares/guest.js`
- Pinia stores in `services/store/` (28 stores, one per domain)
- API client: `services/axios/index.js` — uses `VITE_LARAVEL_API_URL` as base URL, stores token in `localStorage` under `id_token`
- Laravel Echo + Pusher: `services/echo/index.js` — real-time notifications
- Pages split by role: `view/admin/`, `view/client/`, `view/security/`, `view/auth/`
- Layouts: `layout/authLayout.vue` (login/register), `layout/panelLayout.vue` (authenticated app)
- Composables: `composables/usePaginationState.js` — pagination with URL query sync

### Pinia Stores (`frontend/src/resources/services/store/`) — 28 stores
`apartment`, `auth.services`, `booking`, `bookingInvitee`, `budget`, `comunArea`, `configApp`, `credit`, `departmentCharge`, `expense`, `familiar`, `financialAccount`, `guest`, `maintenance`, `monthlyBill`, `multa`, `notice`, `pay`, `payMethod`, `provider`, `quota`, `reports`, `rol`, `serviceCategory`, `transactionCategory`, `users`, `visit`, `waterReadings`

### Frontend Views by Role

**Admin** (`view/admin/`): Dashboard, Users (CRUD + assign property), Departments, Quotas (create + maintenance detail), Payments (register + validate), Reservations, Common Areas, Notices, Reports (pays, bookings, delinquents, expenses, expense matrix, monthly pays), Expenses (CRUD + providers), Monthly Bills, Water Readings, Multas, Budget, Department Charges, Financial Accounts

**Client** (`view/client/`): Dashboard, Quotas (list + detail + detail-v2), Payments (history), Reservations (book + create + list + transparency + view), Notices, Profile, Familiars, Visits, Book of Claims, Payment Methods

**Security** (`view/security/`): Dashboard, Departments, Guests, Visits

**Auth** (`view/auth/`): Login, Register, Forgot Password

### Components (`frontend/src/resources/components/`)
- `admin/users/modal/` — assignUnitModal
- `expenses/` — filter modals
- `finance/` — createFinancialAccountModal, createProviderModal, createTransactionCategoryModal, createExpenseTemplateModal, creditCreateModal, payExpenseModal, viewExpensePayModal
- `layout/` — headerAuth, notificationBell, phoneNumberInput, sideMenu
- `notices/` — announcesList, createAnnouncesModal, createNoticeModal, deleteAnnounceModal, deleteNoticeModal, filterAnnouncesList, noticesList, updateAnnounceModal, updateNoticeModal
- `quotas/` — consolidatedPayModal, quotaChargeDetailModal
- `reports/` — reportFilterModal
- `reserves/` — filterModal, voucherModal
- `shared/` — animatedDigits, currencyDisplay

### Quasar Config
- Plugins: `Notify`, `Dialog`, `Loading`, `BottomSheet`
- Icon set: `eva-icons`
- Sass variables in `frontend/src/resources/plugins/quasar/quasar-variables.sass`:
  - Primary: `#002233`, Secondary: `#0288D1`, Accent: `#FFC107`

### Capacitor Config (`frontend/capacitor.config.json`)
- App ID: `com.example.app`, App Name: `Pacifik`
- Web dir: `dist`
- `allowNavigation`: `192.168.1.241`, `192.168.18.8`, `192.168.1.193`, `edificiopacifik.com`
- Plugin: `CapacitorUpdater` con `autoUpdate: true` (`@capgo/capacitor-updater`)



### Database Schema (49 migrations)
Key tables: `users`, `apartaments`, `comun_areas`, `bookings`, `booking_invitees`, `pays`, `pay_quota` (pivot), `quotas`, `notices`, `familiars`, `guests`, `visits`, `peoples_x_departments`, `multas`, `multa_categories`, `expenses`, `monthly_bills`, `providers`, `service_categories`, `water_readings`, `financial_accounts`, `transactions`, `transaction_categories`, `refunds`, `credit_transactions`, `annual_budgets`, `budget_lines`, `department_charges`, `security_codes`, `pay_methods`

## Conventions

- **Vue**: Composition API with `<script setup>` — always
- **Backend**: Controllers handle HTTP; business logic belongs in Services or Action classes
- **UI Components**: Usar siempre componentes de Quasar (`q-btn`, `q-select`, `q-input`, `q-dialog`, `q-card`, `q-table`, etc.) para la interfaz de usuario y controles interactivos. Vant solo para componentes móviles muy específicos si es necesario.
- **Styling**: Usar siempre las clases de utilidad de Tailwind CSS para maquetación, espaciado, alineación, colores y diseño (`flex`, `grid`, `p-*`, `m-*`, `w-*`, `text-*`, `bg-*`, etc.), evitando CSS custom o estilos en línea innecesarios.
- **Formatting**: Prettier (no semicolons, single quotes, 100 width); PHP via Pint
- **Indentation**: 4 spaces (`.editorconfig`)
- **Icons**: Always use `eva-icons` (prefixed with `eva-`, e.g. `eva-close-outline`, `eva-checkmark-outline`). Never use Material Icons or generic icon names. Custom icons from `svgrepo.com` go in `frontend/src/resources/assets/icons/index.js`
- **Font**: Figtree (configured in `tailwind.config.js`)
- **Currency**: Soles peruanos (S/.) — formatted as `S/. X.XX`

## Testing

- Pest PHP (not PHPUnit directly). Config in `tests/Pest.php`
- `RefreshDatabase` trait is commented out in `tests/Pest.php` — tests hit the real DB
- Only `tests/Feature/ExampleTest.php` exists (basic health check)
- Frontend: Vitest configured in `frontend/vitest.config.js` (jsdom), but no test files exist yet
- No CI/CD pipeline configured

## Gotchas

- Root `vite.config.js` is for the backend's Blade assets (old config, different from `frontend/vite.config.js`). **Always edit `frontend/vite.config.js` for frontend changes.**
- `frontend/dist/` is gitignored — must rebuild before Capacitor sync
- Pusher credentials are duplicated in root `.env` and `frontend/.env` — keep them in sync
- Capacitor `allowNavigation` in `frontend/capacitor.config.json` must include any new staging/dev IPs
- No PHP static analysis (no phpstan/psalm) — rely on IDE + Pint for code quality
- Database is MySQL (`edf_app`), not SQLite
- Tabla `quotas` tiene columna `year` (migración `2026_09_09`) — el `MonthlyQuotaService` la usa para filtrar por año
- `referencias/` folder contains `.md` files documenting each day's changes and Excel source files
- Tailwind v4 is installed in `package.json` but `tailwind.config.js` uses v3-style `module.exports` format
- `Apartament` and `Departament` models coexist — both reference the `apartaments` table
- Frontend PDF generation uses `jspdf` + `jspdf-autotable` + `html2canvas` (client-side), while backend uses `barryvdh/laravel-dompdf` (server-side)
