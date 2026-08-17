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
- 24 API controllers, all under `auth:sanctum` middleware
- Routes defined in `routes/api.php` — all prefixed with `/api/`
- Roles: `admin`, `super-admin`, `propietario`, `trabajador` (enforced via `EnsureUserHasRole` middleware)
- Auth: Sanctum tokens + Fortify (login, registration)
- Real-time: Pusher via Laravel Broadcasting (`config/broadcasting.php`)
- Push notifications: Firebase Cloud Messaging (`kreait/firebase-php`)
- Services layer in `app/Services/` (currently one: `BookingPendingPayNotifier.php`)
- 32 Eloquent models in `app/Models/`

### Frontend (`frontend/src/resources/`)
- `@` alias resolves to `frontend/src/resources/`
- Vue Router with role-based guards: `middlewares/auth.js`, `middlewares/role.js`, `middlewares/guest.js`
- Pinia stores in `services/store/` (23 stores, one per domain)
- API client: `services/axios/index.js` — uses `VITE_LARAVEL_API_URL` as base URL, stores token in localStorage
- Pages split by role: `view/admin/`, `view/client/`, `view/security/`, `view/auth/`
- Layouts: `authLayout.vue` (login/register), `panelLayout.vue` (authenticated app)

### Key env vars
- **Root `.env`**: `DB_HOST`, `DB_DATABASE=edf_app`, `PUSHER_*`, `FIREBASE_CREDENTIALS`
- **`frontend/.env`**: `VITE_LARAVEL_API_URL` (must point to backend, e.g. `http://192.168.1.161:8030`), `VITE_SERVER_IP` (Vite HMR host), `VITE_LARAVEL_MEDIA_URL`

## Conventions

- **Vue**: Composition API with `<script setup>` — always
- **Backend**: Controllers handle HTTP; business logic belongs in Services or Action classes
- **Formatting**: Prettier (no semicolons, single quotes, 100 width); PHP via Pint
- **Indentation**: 4 spaces (`.editorconfig`)
- **UI stack**: Prefer Quasar components first, then Vant for mobile-specific; Tailwind for layout/spacing
- **Icons**: Always use `eva-icons` (prefixed with `eva-`, e.g. `eva-close-outline`, `eva-checkmark-outline`). Never use Material Icons or generic icon names. Custom icons from `svgrepo.com` go in `frontend/src/resources/assets/icons/index.js`
- **Font**: Figtree (configured in `tailwind.config.js`)

## Testing

- Pest PHP (not PHPUnit directly). Config in `tests/Pest.php`
- `RefreshDatabase` trait is commented out in `tests/Pest.php` — tests hit the real DB
- Frontend: Vitest configured in `frontend/vitest.config.js` (jsdom), but no test files exist yet
- No CI/CD pipeline configured

## Gotchas

- Root `vite.config.js` is for the backend's Blade assets (old config, different from `frontend/vite.config.js`). **Always edit `frontend/vite.config.js` for frontend changes.**
- `frontend/dist/` is gitignored — must rebuild before Capacitor sync
- Pusher credentials are duplicated in root `.env` and `frontend/.env` — keep them in sync
- Capacitor `allowNavigation` in `frontend/capacitor.config.json` must include any new staging/dev IPs
- No PHP static analysis (no phpstan/psalm) — rely on IDE + Pint for code quality
- Database is MySQL (`edf_app`), not SQLite
