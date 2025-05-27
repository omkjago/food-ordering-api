<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PembayaranController;
use App\Http\Controllers\Api\PesananController;
use App\Http\Controllers\Api\MejaController;
use App\Http\Controllers\Api\TripayController;
use App\Http\Controllers\Api\SalesReportController;


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

//auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:sanctum');

//guest
//menu

Route::get('/menu', [MenuController::class, 'index']);
Route::get('/menu/favorit', [MenuController::class, 'getFavorit']);

//pesan
Route::post('/pesanan', [PesananController::class, 'store']);
Route::get('/pesanan/{kode_barcode}', [PesananController::class, 'getByBarcode']);
Route::post('/pesanan/{id}/bayar', [PesananController::class, 'bayar']);
Route::get('/pesanan/detail', [PesananController::class, 'detail']);
Route::post('/pesanan/status', [PesananController::class, 'getStatus']);

//payment gatway
Route::prefix('tripay')->group(function () {
    Route::post('/checkout', [TripayController::class, 'checkout']);
    Route::post('/callback', [TripayController::class, 'handleCallback'])->name('tripay.callback');
    Route::post('/order-summary', [TripayController::class, 'orderSummary']);
});
Route::get('/payment/return', [TripayController::class, 'paymentReturn'])->name('tripay.return');


//admin
Route::middleware('auth:sanctum', 'is_admin')->group(function () {
    Route::get('/admin/pesanan/aktif', [AdminController::class, 'aktif']);
    Route::get('/admin/pesanan/pending', [AdminController::class, 'pending']);
    Route::get('/admin/menu', [AdminController::class, 'menu']);
    Route::post('/admin/menu', [AdminController::class, 'storeMenu']);
    Route::post('/admin/menu/{id}', [AdminController::class, 'updateMenu']);
    Route::get('/admin/menu/{id}', [AdminController::class, 'menuId']);
    Route::delete('/admin/menu/{id}', [AdminController::class, 'deleteMenu']);
    Route::post('admin/confirm-payment', [PesananController::class, 'confirmPayment']);
    Route::get('pesanan/by-order-token/{order_token}', [PesananController::class, 'getByOrderToken']);

    Route::get('/admin/statistics', [AdminController::class, 'statistics']);
    Route::get('/admin/popular-products', [AdminController::class, 'popularProducts']);
    Route::get('/admin/recent-orders', [AdminController::class, 'recentOrders']);
    Route::get('/admin/reports/sales', [SalesReportController::class, 'generateReport']);
    Route::get('/admin/reports/sales/download', [SalesReportController::class, 'downloadReport']);
    Route::get('/admin/reports/sales?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD', [SalesReportController::class, 'generateReport']);

});

Route::get('/meja', [MejaController::class, 'index']);
