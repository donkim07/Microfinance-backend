<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductCatalogController;
use App\Http\Controllers\Api\LoanController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Product Catalog Routes
Route::post('/product-catalog', [ProductCatalogController::class, 'processProductCatalog']);
Route::post('/product-decommission', [ProductCatalogController::class, 'processProductDecommission']);

// Loan Routes
Route::post('/loan-charges', [LoanController::class, 'calculateLoanCharges']);
Route::post('/loan-offer-request', [LoanController::class, 'processLoanOfferRequest']);
Route::post('/loan-initial-approval', [LoanController::class, 'processLoanInitialApproval']);
Route::post('/loan-final-approval', [LoanController::class, 'processLoanFinalApproval']);
Route::post('/loan-disbursement', [LoanController::class, 'processLoanDisbursement']);
Route::post('/loan-disbursement-failure', [LoanController::class, 'processLoanDisbursementFailure']);
Route::post('/loan-cancellation', [LoanController::class, 'processLoanCancellation']);
Route::post('/loan-repayment', [LoanController::class, 'processLoanRepayment']);
Route::post('/loan-liquidation', [LoanController::class, 'processLoanLiquidation']);
Route::post('/loan-status', [LoanController::class, 'processLoanStatusRequest']);

// For other loan types like top-up, takeover, restructuring, we'll use the same controller methods
// but with different message types in the request body 