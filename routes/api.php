<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

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

// ESS Utumishi API integration routes
Route::middleware('api.auth')->prefix('v1')->group(function () {
    // Product Catalog API
    Route::get('/product-catalogs', [ApiController::class, 'getProductCatalogs']);
    Route::get('/product-catalogs/{id}', [ApiController::class, 'getProductCatalog']);
    
    // Loan API
    Route::post('/loans', [ApiController::class, 'createLoan']);
    Route::post('/loans/{id}/top-up', [ApiController::class, 'topUpLoan']);
    Route::post('/loans/{id}/restructure', [ApiController::class, 'restructureLoan']);
    Route::post('/loans/{id}/takeover', [ApiController::class, 'takeoverLoan']);
    Route::post('/loans/{id}/repayment', [ApiController::class, 'repayLoan']);
    Route::post('/loans/{id}/default', [ApiController::class, 'defaultLoan']);
    Route::get('/loans/{id}/status', [ApiController::class, 'getLoanStatus']);
    
    // Account Validation API
    Route::post('/validate-account', [ApiController::class, 'validateAccount']);
    
    // Bank Branch API
    Route::get('/banks', [ApiController::class, 'getBanks']);
    Route::get('/banks/{id}/branches', [ApiController::class, 'getBankBranches']);
    
    // Deduction API
    Route::get('/deductions', [ApiController::class, 'getDeductions']);
    Route::post('/deductions', [ApiController::class, 'createDeduction']);
    Route::put('/deductions/{id}', [ApiController::class, 'updateDeduction']);
    
    // Digital Signature API
    Route::post('/sign-document', [ApiController::class, 'signDocument']);
    Route::get('/verify-signature/{id}', [ApiController::class, 'verifySignature']);
});

// Internal API routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Notification API
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'apiIndex']);
    Route::put('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'apiMarkAsRead']);
    
    // Dashboard API
    Route::get('/dashboard/stats', [App\Http\Controllers\Admin\DashboardController::class, 'getStats']);
    Route::get('/dashboard/charts/loans', [App\Http\Controllers\Admin\DashboardController::class, 'getLoansChart']);
    Route::get('/dashboard/charts/repayments', [App\Http\Controllers\Admin\DashboardController::class, 'getRepaymentsChart']);
}); 