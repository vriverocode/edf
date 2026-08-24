<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankAccountController;
use App\Http\Controllers\Api\BillInvoiceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ComunAreaController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DepartamentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinancialAccountController;
use App\Http\Controllers\Api\GuestListController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\MonthlyBillsController;
use App\Http\Controllers\Api\MultaController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PayController;
use App\Http\Controllers\Api\PayMethodController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\QuotaController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\TransactionCategoryController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\WaterReadingController;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── PUBLIC ROUTES ───────────────────────────────────────────
Route::middleware('throttle:public')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/validate-reset-token', [ResetPasswordController::class, 'validateToken']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset']);
});
Route::get('/app-version', [ConfigController::class, 'getAppVersion']);

// ─── TEST ROUTES (kept per user request) ─────────────────────
Route::post('/pruebaCorreo', [PayController::class, 'claimsByPay']);
Route::post('/prueba/notify', [UserController::class, 'pruebaRealtimeNotification']);

// ─── AUTHENTICATED ROUTES ────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Auth / User profile ──────────────────────────────────
    Route::get('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        $user = $request->user()->load(['rol', 'airbnbDepartment.departament', 'bankAccounts', 'availableComunAreas', 'units' => function ($query) {
            $query->withCount('pendingQuotas')->withSum('pendingQuotas', 'amount');
        }, 'departmentsInquilino' => function ($query) {
            $query->with(['departament' => function ($q) {
                $q->withCount('pendingQuotas')->withSum('pendingQuotas', 'amount');
            }]);
        }]);

        $currency = Currency::first();

        $user->total_peding_quotas = $user->units->sum('pending_quotas_sum_amount');
        $userData = $user->toArray();
        $userData['currency'] = $currency?->id ?? 1;
        $userData['currency_symbol'] = $currency?->symbol ?? 'S/';

        return $userData;
    });
    Route::middleware('auth:sanctum')->post('/token-movile', [UserController::class, 'saveTokenMovile']);
    Route::put('/profile', [UserController::class, 'updateProfile'])->middleware('throttle:write');

    // ── Users ────────────────────────────────────────────────
    Route::get('users/show/{id}', [UserController::class, 'show'])->middleware('role:admin,super-admin,trabajador');
    Route::prefix('users')->name('user.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [UserController::class, 'getOwners']);
        Route::get('/byId/{id}', [UserController::class, 'getUserById']);
        Route::get('/get-resident', [UserController::class, 'getResident']);
        Route::get('/without-apartment', [UserController::class, 'getOwnersWithoutApartment']);
        Route::get('/with-publish', [UserController::class, 'getAllUserWithPublish']);
        Route::get('/admin/get_pendings', [UserController::class, 'getCountPendingsForAdmin'])->middleware('role:admin,super-admin');
        Route::get('/resident/{id}/bookings', [UserController::class, 'getResidentBookings']);

        // Write - ownership or admin
        Route::post('/', [UserController::class, 'store'])->middleware('throttle:sensitive');
        Route::post('/byPropietario', [UserController::class, 'store'])->middleware('throttle:sensitive');
        Route::post('/temporary-or-resident', [UserController::class, 'storeResidentUser'])->middleware('throttle:sensitive');
        Route::post('/complete-first-time', [UserController::class, 'completeFirstTime']);
        Route::post('/{user}/available-areas', [UserController::class, 'setAvailableComunAreaToReserve'])->middleware('role:admin,super-admin,propietario');
        Route::delete('/d/{id}', [UserController::class, 'destroy'])->middleware('throttle:write');
        Route::post('/resident/u/{id}', [UserController::class, 'updateResident'])->middleware('throttle:write');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/assing_apartmet', [DepartamentController::class, 'assingApartment'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/assign-property', [DepartamentController::class, 'assingApartment'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Apartments ───────────────────────────────────────────
    Route::prefix('apartments')->name('apartment.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [DepartamentController::class, 'paginationApartment']);
        Route::get('/byId/{id}', [DepartamentController::class, 'getApartmentById']);
        Route::get('/byFind', [DepartamentController::class, 'apartmentsByfind']);
        Route::get('/byUser', [DepartamentController::class, 'getApartmentsByUser']);
        // Write - admin only
        Route::post('/', [DepartamentController::class, 'storeApartment'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [DepartamentController::class, 'updateApartment'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Comun Areas ──────────────────────────────────────────
    Route::prefix('comun-area')->name('comun.area.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [ComunAreaController::class, 'paginationAreas']);
        Route::get('/all', [ComunAreaController::class, 'getAll']);
        Route::get('/byId/{id}', [ComunAreaController::class, 'comunAreaById']);
        Route::get('/bySearch', [ComunAreaController::class, 'AreasBySearch']);
        // Write - admin only
        Route::post('/', [ComunAreaController::class, 'storeArea'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [ComunAreaController::class, 'updateArea'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/d/{id}', [ComunAreaController::class, 'deleteArea'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/toggle-status/{id}', [ComunAreaController::class, 'toggleAreaStatus'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Rules ────────────────────────────────────────────────
    Route::prefix('rules')->name('rule.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [RuleController::class, 'index']);
        Route::get('/byId/{id}', [RuleController::class, 'ruleById']);
        // Write - admin only
        Route::post('/', [RuleController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [RuleController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/d/{id}', [RuleController::class, 'deleteRule'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Multas ───────────────────────────────────────────────
    Route::prefix('multas')->name('multa.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [MultaController::class, 'index']);
        Route::get('/byId/{id}', [MultaController::class, 'multaById']);
        // Write - admin only
        Route::post('/', [MultaController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [MultaController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/d/{id}', [MultaController::class, 'deleteMulta'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Bookings ─────────────────────────────────────────────
    Route::prefix('bookings')->name('booking.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [BookingController::class, 'getBookingsByUser']);
        Route::get('/availableBooking/{id}', [BookingController::class, 'getAvaibleBookingByDay']);
        Route::get('/byId/{id}', [BookingController::class, 'getBookingById']);
        Route::get('/byArea/{id}', [BookingController::class, 'getBookingByAreaId']);
        Route::get('/byDepartment/{id}', [BookingController::class, 'getBookingsByDepartment']);
        Route::get('/extension-slots/{id}', [BookingController::class, 'getExtensionSlots']);
        // Write - ownership checked in controller
        Route::post('/', [BookingController::class, 'storeBooking'])->middleware('throttle:sensitive');
        Route::post('/extension', [BookingController::class, 'storeExtension'])->middleware('throttle:sensitive');
        Route::post('/cancel/{id}', [BookingController::class, 'cancelBooking'])->middleware('throttle:write');
        // Admin only
        Route::get('/pendings', [BookingController::class, 'getPendings'])->middleware('role:admin,super-admin');
    });

    // Read-only: accesible para el trabajador (detalles de reserva en el módulo de seguridad)
    Route::get('/bookings/byId/{id}', [BookingController::class, 'getBookingById']);

    // Transparencia: todas las reservas (todos los roles autenticados)
    Route::get('/bookings/all', [BookingController::class, 'getAllBookings']);
    Route::get('/bookings/export', [BookingController::class, 'exportBookings']);

    // Opciones de usuario (para selects, todos los roles autenticados)
    Route::get('/users/options', [UserController::class, 'getUsersOptions']);

    // ── Guest Lists ──────────────────────────────────────────
    Route::prefix('bookings/{id}/guests')->name('booking.guests.')->middleware('role_not:trabajador')->group(function () {
        Route::get('/', [GuestListController::class, 'getByBooking']);
        Route::post('/', [GuestListController::class, 'store'])->middleware('throttle:write');
    });
    Route::prefix('guests')->name('guests.')->middleware('role_not:trabajador')->group(function () {
        Route::put('/{id}', [GuestListController::class, 'update'])->middleware('throttle:write');
        Route::delete('/{id}', [GuestListController::class, 'destroy'])->middleware('throttle:write');
    });

    // ── Events ───────────────────────────────────────────────
    Route::prefix('events')->name('event.')->group(function () {
        // Read
        Route::get('/', [EventController::class, 'get']);
        Route::get('/byId/{id}', [EventController::class, 'show']);
        Route::get('/attendance/{id}', [EventController::class, 'attendance'])->middleware('role:admin,super-admin');
        Route::post('/set-assists/{id}', [EventController::class, 'setAssist'])->middleware('throttle:write');
        // Write - admin only
        Route::post('/', [EventController::class, 'create'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::delete('/{id}', [EventController::class, 'destroy'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/{id}', [EventController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/send-reminder/{id}', [EventController::class, 'sendReminderEvent'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Payments ─────────────────────────────────────────────
    Route::prefix('pays')->name('pay.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [PayController::class, 'getPaysByUser']);
        Route::get('/byId/{id}', [PayController::class, 'getPayById']);
        Route::get('/claims/sequence', [PayController::class, 'getClaimSequence']);
        // Write - ownership checked in controller
        Route::post('/bookings', [PayController::class, 'storePay'])->middleware('throttle:payment');
        Route::post('/quotas', [PayController::class, 'storePay'])->middleware('throttle:payment');
        Route::post('/culqi-payment', [PayController::class, 'processCulqiPayment'])->middleware('throttle:payment');
        Route::post('/claims', [PayController::class, 'claimsByPay'])->middleware('throttle:payment');
        // Admin only
        Route::post('/updateStatus/{id}', [PayController::class, 'updateStatus'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/validate/{id}', [PayController::class, 'validatePayment'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/refund', [PayController::class, 'refund'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/refund/notify-missing-bank-account', [PayController::class, 'notifyMissingBankAccountRequest'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::get('/receipt/{payId}', [PayController::class, 'downloadBookingReceipt']);
    });

    // ── Notifications ────────────────────────────────────────
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });

    // ── Quotas ───────────────────────────────────────────────
    Route::prefix('quotas')->name('quota.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [QuotaController::class, 'index']);
        Route::get('/byMonth/{id}', [QuotaController::class, 'getByMonth']);
        Route::get('/byPay/{id}', [QuotaController::class, 'getByPay']);
        Route::get('/byId/{id}', [QuotaController::class, 'show']);
        Route::get('/client-water-detail/{id}', [QuotaController::class, 'clientWaterDetail']);
        Route::get('/client-maintenance-detail/{id}', [QuotaController::class, 'clientMaintenanceDetail']);
        // Write - Admin only
        Route::post('/', [QuotaController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/generate', [QuotaController::class, 'generate'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::put('/{quota}', [QuotaController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::delete('/{quota}', [QuotaController::class, 'destroy'])->middleware('role:admin,super-admin', 'throttle:write');
        // Admin only - reports
        Route::get('/admin/monthly-summary', [QuotaController::class, 'adminMonthlySummary'])->middleware('role:admin,super-admin');
        Route::get('/admin/by-month/{month}', [QuotaController::class, 'adminGroupedByOwnerForMonth'])->middleware('role:admin,super-admin');
    });

    // ── Visits ───────────────────────────────────────────────
    Route::prefix('visits')->name('visit.')->group(function () {
        Route::get('/', [VisitController::class, 'getVisitsByUser']);
        Route::get('/search', [VisitController::class, 'getVisitsByUser']);
        Route::get('/filter-options', [VisitController::class, 'getVisitFilterOptionsByUser']);
        Route::get('/byId/{id}', [VisitController::class, 'show']);
        Route::post('/', [VisitController::class, 'storeVisit'])->middleware('throttle:sensitive');
        Route::delete('/{id}', [VisitController::class, 'destroy'])->middleware('throttle:write');
    });

    // ── Security (trabajador) ────────────────────────────────
    Route::prefix('security')->name('security.visit.')->middleware('role:trabajador')->group(function () {
        Route::get('/visits', [VisitController::class, 'getVisitsForSecurity']);
        Route::get('/visits/search', [VisitController::class, 'getVisitsForSecurity']);
        Route::get('/visits/filter-options', [VisitController::class, 'getVisitFilterOptionsForSecurity']);
        Route::post('/visits/arrived/{id}', [VisitController::class, 'markVisitArrived']);
        Route::get('/airbnb', [VisitController::class, 'getAirbnbForSecurity']);
        Route::get('/airbnb/filter-options', [VisitController::class, 'getAirbnbFilterOptionsForSecurity']);
        Route::get('/bookings', [BookingController::class, 'getBookingsForSecurity']);
        Route::post('/bookings/cancel-maintenance/{id}', [BookingController::class, 'cancelBookingForMaintenance']);
        Route::post('/bookings/complete/{id}', [BookingController::class, 'completeBooking']);
        Route::get('/departments/inhabited', [DepartamentController::class, 'getInhabitedDepartments']);
        Route::get('/departments/{id}/residents', [DepartamentController::class, 'getDepartmentResidents']);
    });

    // ── Notices ──────────────────────────────────────────────
    Route::prefix('notices')->name('notice.')->middleware('role_not:trabajador')->group(function () {
        // Read — accessible to all authenticated users
        Route::get('/', [NoticeController::class, 'index']);
        Route::get('/byId/{id}', [NoticeController::class, 'show']);
        // Mark as viewed — any authenticated user
        Route::post('/set-viewer/{id}', [NoticeController::class, 'setViewer']);
        // Admin only — MUST be before {id} wildcard
        Route::post('/set-new-status/{id}', [NoticeController::class, 'setNewStatus'])->middleware('role:admin,super-admin');
        // Write — type 1 (noticias) admin-only, type 2 (anuncios) any user
        Route::post('/', [NoticeController::class, 'store'])->middleware('throttle:sensitive');
        Route::delete('/{id}', [NoticeController::class, 'delete'])->middleware('throttle:write');
        Route::post('/{id}', [NoticeController::class, 'update'])->middleware('throttle:write');
    });

    // ── Maintenances ─────────────────────────────────────────
    Route::prefix('maintenances')->name('maintenance.')->group(function () {
        // Read — all authenticated users
        Route::get('/', [MaintenanceController::class, 'index']);
        Route::get('/by-area/{id}', [MaintenanceController::class, 'getByArea']);
        Route::get('/{id}', [MaintenanceController::class, 'show']);
        // Write - admin only
        Route::post('/', [MaintenanceController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        // Write - admin + trabajador (security)
        Route::post('/{id}/complete', [MaintenanceController::class, 'complete'])->middleware('role:admin,super-admin,trabajador', 'throttle:write');
        Route::post('/{id}/status', [MaintenanceController::class, 'changeStatus'])->middleware('role:admin,super-admin,trabajador', 'throttle:write');
        Route::post('/{id}/update', [MaintenanceController::class, 'update'])->middleware('role:admin,super-admin,trabajador', 'throttle:write');
        Route::delete('/{id}', [MaintenanceController::class, 'destroy'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Pay Methods ──────────────────────────────────────────
    Route::prefix('pay-method')->name('payMethod.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [PayMethodController::class, 'index']);
        Route::get('/byId/{id}', [PayMethodController::class, 'show']);
        // Write - admin only
        Route::post('/', [PayMethodController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [PayMethodController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/status/{id}', [PayMethodController::class, 'updateStatus'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Monthly Bills ────────────────────────────────────────
    Route::prefix('monthly-bills')->name('monthlyBills.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [MonthlyBillsController::class, 'index']);
        Route::get('/exists-for-period', [MonthlyBillsController::class, 'existsForPeriod']);
        Route::get('/byId/{id}', [MonthlyBillsController::class, 'show']);
        // Write - admin only
        Route::post('/', [MonthlyBillsController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [MonthlyBillsController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/generate-quotas/{id}', [MonthlyBillsController::class, 'generateQuotas'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Water Readings ───────────────────────────────────────
    Route::prefix('water-readings')->name('waterReadings.')->group(function () {
        // Read - admin only (financial data)
        Route::get('/', [WaterReadingController::class, 'index'])->middleware('role:admin,super-admin');
        Route::get('/byId/{id}', [WaterReadingController::class, 'show'])->middleware('role:admin,super-admin');
        Route::get('/last-by-department/{departmentId}', [WaterReadingController::class, 'getLastByDepartment'])->middleware('role:admin,super-admin');
        // Write - admin only
        Route::post('/', [WaterReadingController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [WaterReadingController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Bill Invoices ────────────────────────────────────────
    Route::prefix('bill-invoices')->name('bill-invoice.')->middleware('role:admin,super-admin')->group(function () {
        Route::get('/by-quota/{quotaId}', [BillInvoiceController::class, 'show']);
        Route::get('/download/{quotaId}', [BillInvoiceController::class, 'downloadPdf'])->name('download');
        Route::get('/preview/{quotaId}', [BillInvoiceController::class, 'previewHtml']);
        Route::post('/send-email/{quotaId}', [BillInvoiceController::class, 'sendEmail'])->middleware('throttle:write');
        Route::post('/send-bulk/{monthlyBillId}', [BillInvoiceController::class, 'sendBulkEmails'])->middleware('throttle:write');
        Route::get('/test/list-paid', [BillInvoiceController::class, 'listPaidQuotas']);
        Route::post('/test/send/{quotaId}', [BillInvoiceController::class, 'testSend']);
    });
    Route::get('/bill-invoices/client-download/{quotaId}', [BillInvoiceController::class, 'clientDownloadPdf'])->middleware('auth:sanctum', 'role_not:trabajador');

    // ── Transaction Categories ───────────────────────────────
    Route::prefix('transaction-categories')->name('transactionCategories.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [TransactionCategoryController::class, 'index']);
        // Write - admin only
        Route::post('/', [TransactionCategoryController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Service Categories ───────────────────────────────────
    Route::prefix('service-categories')->name('serviceCategories.')->middleware('role_not:trabajador')->group(function () {
        // Read
        Route::get('/', [ServiceCategoryController::class, 'index']);
        // Write - admin only
        Route::post('/', [ServiceCategoryController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [ServiceCategoryController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::delete('/d/{id}', [ServiceCategoryController::class, 'destroy'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Financial Accounts ───────────────────────────────────
    Route::prefix('financial-accounts')->name('financialAccounts.')->group(function () {
        // Read - admin only (financial data)
        Route::get('/', [FinancialAccountController::class, 'index'])->middleware('role:admin,super-admin');
        Route::get('/currencies', [FinancialAccountController::class, 'currencies'])->middleware('role:admin,super-admin');
        Route::get('/byId/{id}', [FinancialAccountController::class, 'show'])->middleware('role:admin,super-admin');
        // Write - admin only
        Route::post('/', [FinancialAccountController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [FinancialAccountController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/status/{id}', [FinancialAccountController::class, 'updateStatus'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Providers ────────────────────────────────────────────
    Route::prefix('providers')->name('providers.')->middleware('role:admin,super-admin')->group(function () {
        // Read
        Route::get('/', [ProviderController::class, 'index']);
        Route::get('/byId/{id}', [ProviderController::class, 'show']);
        // Write
        Route::post('/', [ProviderController::class, 'store'])->middleware('throttle:write');
        Route::post('/u/{id}', [ProviderController::class, 'update'])->middleware('throttle:write');
        Route::delete('/d/{id}', [ProviderController::class, 'destroy'])->middleware('throttle:write');
    });

    // ── Bank Accounts ────────────────────────────────────────
    Route::prefix('bank-accounts')->name('bankAccounts.')->group(function () {
        Route::get('/', [BankAccountController::class, 'index']);
        Route::get('/{id}', [BankAccountController::class, 'show']);
        Route::post('/', [BankAccountController::class, 'store'])->middleware('throttle:write');
        Route::put('/{id}', [BankAccountController::class, 'update'])->middleware('throttle:write');
        Route::delete('/{id}', [BankAccountController::class, 'destroy'])->middleware('throttle:write');
    });

    // ── Incidents ────────────────────────────────────────────
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index']);
        Route::get('/byId/{id}', [IncidentController::class, 'show']);
        Route::post('/', [IncidentController::class, 'store'])->middleware('throttle:write');
        Route::post('/u/{id}', [IncidentController::class, 'update'])->middleware('role:admin,super-admin,trabajador', 'throttle:write');
    });

    // ── Expenses ─────────────────────────────────────────────
    Route::prefix('expenses')->name('expense.')->group(function () {
        // Read - admin only (financial data)
        Route::get('/', [ExpenseController::class, 'index'])->middleware('role:admin,super-admin');
        Route::get('/unassigned', [ExpenseController::class, 'unassigned'])->middleware('role:admin,super-admin');
        Route::get('/form-options', [ExpenseController::class, 'formOptions'])->middleware('role:admin,super-admin');
        Route::get('/byId/{id}', [ExpenseController::class, 'show'])->middleware('role:admin,super-admin');
        // Write - admin only
        Route::post('/', [ExpenseController::class, 'store'])->middleware('role:admin,super-admin', 'throttle:write');
        Route::post('/u/{id}', [ExpenseController::class, 'update'])->middleware('role:admin,super-admin', 'throttle:write');
    });

    // ── Reports ──────────────────────────────────────────────
    Route::prefix('reports')->name('report.')->middleware('role:admin,super-admin')->group(function () {
        Route::get('/bookings', [ReportController::class, 'bookings']);
        Route::get('/bookings/export', [ReportController::class, 'exportBookings']);
        Route::get('/bookings/metrics', [ReportController::class, 'bookingsMetrics']);
        Route::get('/monthly-payments', [ReportController::class, 'monthlyPayments']);
        Route::get('/delinquents', [ReportController::class, 'delinquents']);
        Route::post('/delinquents/send-reminder', [ReportController::class, 'sendReminderDelinquents']);
    });
});
