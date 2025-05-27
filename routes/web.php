<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\FSPController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\BankBranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\FinancialServiceProviderController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\JobClassController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LoanApplicationController;
use App\Http\Controllers\LoanApprovalController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanDefaultController;
use App\Http\Controllers\LoanDisbursementController;
use App\Http\Controllers\LoanRepaymentController;
use App\Http\Controllers\LoanRestructureController;
use App\Http\Controllers\LoanTakeoverController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProductCatalogController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');

// Language Routes
Route::get('/language/{locale}', [LanguageController::class, 'changeLanguage'])->name('language.change');

// User Dashboard Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Loan Routes
    Route::resource('loan-applications', LoanApplicationController::class);
    Route::resource('loans', LoanController::class);
    Route::resource('loan-repayments', LoanRepaymentController::class);

    // FSP Routes
    Route::get('/financial-service-providers', [FinancialServiceProviderController::class, 'index'])->name('fsps.index');
    Route::get('/financial-service-providers/{fsp}', [FinancialServiceProviderController::class, 'show'])->name('fsps.show');
    
    // Product Catalog Routes
    Route::get('/product-catalogs', [ProductCatalogController::class, 'index'])->name('product-catalogs.index');
    Route::get('/product-catalogs/{productCatalog}', [ProductCatalogController::class, 'show'])->name('product-catalogs.show');

    // Bank Routes
    Route::get('/banks', [BankController::class, 'index'])->name('banks.index');
    Route::get('/banks/{bank}', [BankController::class, 'show'])->name('banks.show');
    Route::get('/bank-branches', [BankBranchController::class, 'index'])->name('bank-branches.index');
    
    // User Profile Routes
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    
    // Notification Routes
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

// Admin Routes
Route::middleware(['auth', 'role:admin|super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // User Management
    Route::resource('users', UserController::class);
    
    // Role & Permission Management
    Route::resource('roles', RoleController::class);
    Route::resource('permissions', PermissionController::class);
    
    // FSP Management
    Route::resource('fsps', FSPController::class);
    
    // Institution Management
    Route::resource('institutions', InstitutionController::class);
    Route::resource('departments', DepartmentController::class);
    Route::resource('designations', DesignationController::class);
    Route::resource('job-classes', JobClassController::class);
    
    // Bank Management
    Route::resource('banks', BankController::class);
    Route::resource('bank-branches', BankBranchController::class);
    
    // Loan Management
    Route::resource('loan-applications', LoanApplicationController::class);
    Route::resource('loans', LoanController::class);
    Route::resource('loan-approvals', LoanApprovalController::class);
    Route::resource('loan-disbursements', LoanDisbursementController::class);
    Route::resource('loan-repayments', LoanRepaymentController::class);
    Route::resource('loan-restructures', LoanRestructureController::class);
    Route::resource('loan-takeovers', LoanTakeoverController::class);
    Route::resource('loan-defaults', LoanDefaultController::class);
    
    // Product Management
    Route::resource('product-catalogs', ProductCatalogController::class);
    
    // Language Management
    Route::resource('languages', LanguageController::class);
});
