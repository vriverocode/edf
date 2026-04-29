<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\QuotaController;
use App\Http\Controllers\Api\PayController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ComunAreaController;
use App\Http\Controllers\Api\DepartamentController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RuleController;
use App\Http\Controllers\Api\MultaController;
use App\Http\Controllers\Api\PayMethodController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\MonthlyBillsController;
use App\Http\Controllers\Api\WaterReadingController;
use App\Http\Controllers\Api\FinancialAccountController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/prueba/notify', [UserController::class, 'pruebaRealtimeNotification']);

Route::middleware('auth:sanctum')->post('/token-movile', [UserController::class, 'saveTokenMovile']);
Route::middleware('auth:sanctum')->group(function () {
    //--- Login/Auth ---
    Route::get('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user()->load(['rol', 'apartaments']);
    });

    Route::prefix('users')->name('user.')->group(function () {
        Route::get('/', [UserController::class, 'getOwners']);
        Route::get('/get-resident', [UserController::class, 'getResident']);

        Route::post('/', [UserController::class, 'store']);
        Route::post('/byPropietario', [UserController::class, 'store']);
        Route::post('/temporary-or-resident', [UserController::class, 'storeResidentUser']);

        Route::post('/assing_apartmet', [DepartamentController::class, 'assingApartment']);
        Route::get('/admin/get_pendings', [UserController::class, 'getCountPendingsForAdmin']);
        Route::get('/with-publish', [UserController::class, 'getAllUserWithPublish']);
    });
    Route::prefix('apartments')->name('apartment.')->group(function () {
        Route::get('/', [DepartamentController::class, 'paginationApartment']);
        Route::post('/', [DepartamentController::class, 'storeApartment']);
        Route::get('/byFind', [DepartamentController::class, 'apartmentsByfind']);
        Route::get('/byUser', [DepartamentController::class, 'getApartmentsByUser']);
    });
    Route::prefix('comun-area')->name('comun.area.')->group(function () {
        Route::get('/', [ComunAreaController::class, 'paginationAreas']);
        Route::get('/all', [ComunAreaController::class, 'getAll']);
        Route::get('/byId/{id}', [ComunAreaController::class, 'comunAreaById']);
        Route::get('/bySearch', [ComunAreaController::class, 'AreasBySearch']);
        Route::post('/', [ComunAreaController::class, 'storeArea']);
        Route::post('/u/{id}', [ComunAreaController::class, 'updateArea']);
        Route::post('/d/{id}', [ComunAreaController::class, 'deleteArea']);
    });
    Route::prefix('rules')->name('rule.')->group(function () {
        Route::get('/', [RuleController::class, 'index']);
        Route::get('/byId/{id}', [RuleController::class, 'ruleById']);
        Route::post('/', [RuleController::class, 'store']);
        Route::post('/u/{id}', [RuleController::class, 'update']);
        Route::post('/d/{id}', [RuleController::class, 'deleteRule']);
    });
    Route::prefix('multas')->name('multa.')->group(function () {
        Route::get('/', [MultaController::class, 'index']);
        Route::get('/byId/{id}', [MultaController::class, 'multaById']);
        Route::post('/', [MultaController::class, 'store']);
        Route::post('/u/{id}', [MultaController::class, 'update']);
        Route::post('/d/{id}', [MultaController::class, 'deleteMulta']);                                                                                        
    });
    Route::prefix('bookings')->name('booking.')->group(function () {
        Route::get('/', [BookingController::class, 'getBookingsByUser']);
        Route::post('/', [BookingController::class, 'storeBooking']);
        Route::get('/availableBooking/{id}', [BookingController::class, 'getAvaibleBookingByDay']);
        Route::get('/byId/{id}', [BookingController::class, 'getBookingById']);
        Route::get('/byArea/{id}', [BookingController::class, 'getBookingByAreaId']);
        Route::post('/cancel/{id}', [BookingController::class, 'cancelBooking']);
        Route::get('/pendings', [BookingController::class, 'getPendings']);
    });
    Route::prefix('events')->name('event.')->group(function () {
        Route::get('/', [EventController::class, 'get']);
        Route::post('/', [EventController::class, 'create']);
        Route::get('/byId/{id}', [EventController::class, 'show']);
        Route::delete('/{id}', [EventController::class, 'destroy']);
        Route::post('/{id}', [EventController::class, 'update']);
        Route::post('/set-assits/{id}', [EventController::class, 'setAssist']);
        // Route::get('/availableBooking/{id}', [EventController::class, 'getAvaibleBookingByDay']);
        // Route::get('/byArea/{id}', [EventController::class, 'getBookingByAreaId']);
        // Route::post('/cancel/{id}', [EventController::class, 'cancelBooking']);
        // Route::get('/pendings', [EventController::class, 'getPendings']);
    });
    Route::prefix('pays')->name('pay.')->group(function () {
        Route::get('/', [PayController::class, 'getPaysByUser']);
        Route::post('/bookings', [PayController::class, 'storePay']);
        Route::post('/quotas', [PayController::class, 'storePay']);
        Route::get('/byId/{id}', [PayController::class, 'getPayById']);
        Route::post('/updateStatus/{id}', [PayController::class, 'updateStatus']);
        Route::post('/culqi-payment', [PayController::class, 'processCulqiPayment']);
    });
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
    });
    Route::prefix('quotas')->name('quota.')->group(function () {
        Route::get('/', [QuotaController::class, 'index']);
        Route::get('/byId/{id}', [QuotaController::class, 'show']);
    });
    Route::prefix('visits')->name('visit.')->group(function () {
        Route::get('/', [VisitController::class, 'getVisitsByUser']);
        Route::get('/search', [VisitController::class, 'getVisitsByUser']);
        Route::get('/filter-options', [VisitController::class, 'getVisitFilterOptionsByUser']);
        Route::post('/', [VisitController::class, 'storeVisit']);
    });
    Route::prefix('security')->name('security.visit.')->middleware('role:trabajador')->group(function () {
        Route::get('/visits', [VisitController::class, 'getVisitsForSecurity']);
        Route::get('/visits/search', [VisitController::class, 'getVisitsForSecurity']);
        Route::get('/visits/filter-options', [VisitController::class, 'getVisitFilterOptionsForSecurity']);
        Route::post('/visits/arrived/{id}', [VisitController::class, 'markVisitArrived']);
        Route::get('/airbnb', [VisitController::class, 'getAirbnbForSecurity']);
        Route::get('/airbnb/filter-options', [VisitController::class, 'getAirbnbFilterOptionsForSecurity']);
    });
    Route::prefix('notices')->name('notice.')->group(function () {
        Route::get('/', [NoticeController::class, 'index']);
        Route::get('/byId/{id}', [NoticeController::class, 'show']);
        Route::post('/', [NoticeController::class, 'store']);
        Route::post('/set-viewer/{id}', [NoticeController::class, 'setViewer']);
        Route::post('/set-new-status/{id}', [NoticeController::class, 'setNewStatus']);
        Route::delete('/{id}', [NoticeController::class, 'delete']);
        Route::post('/{id}', [NoticeController::class, 'update']);
    });
    Route::prefix('pay-method')->name('payMethod.')->group(function () {
        Route::get('/', [PayMethodController::class, 'index']);
        Route::post('/', [PayMethodController::class, 'store']);
        Route::get('/byId/{id}', [PayMethodController::class, 'show']);
        Route::post('/u/{id}', [PayMethodController::class, 'update']);
        Route::post('/status/{id}', [PayMethodController::class, 'updateStatus']);
    });

    Route::prefix('monthly-bills')->name('monthlyBills.')->group(function () {
        Route::get('/', [MonthlyBillsController::class, 'index']);
        Route::get('/byId/{id}', [MonthlyBillsController::class, 'show']);
        Route::post('/', [MonthlyBillsController::class, 'store']);
        Route::post('/u/{id}', [MonthlyBillsController::class, 'update']);
    });

    Route::prefix('water-readings')->name('waterReadings.')->group(function () {
        Route::get('/', [WaterReadingController::class, 'index']);
        Route::get('/byId/{id}', [WaterReadingController::class, 'show']);
        Route::post('/', [WaterReadingController::class, 'store']);
        Route::post('/u/{id}', [WaterReadingController::class, 'update']);
    });

    Route::prefix('financial-accounts')->name('financialAccounts.')->group(function () {
        Route::get('/', [FinancialAccountController::class, 'index']);
        Route::get('/currencies', [FinancialAccountController::class, 'currencies']);
        Route::get('/byId/{id}', [FinancialAccountController::class, 'show']);
        Route::post('/', [FinancialAccountController::class, 'store']);
        Route::post('/u/{id}', [FinancialAccountController::class, 'update']);
        Route::post('/status/{id}', [FinancialAccountController::class, 'updateStatus']);
    });
});
