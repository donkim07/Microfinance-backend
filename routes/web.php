<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LanguageController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/signup', function () {
    return view('registration/signup');
});
Route::get('/login', function () {
    return view('registration/login');
});

// Language switching
Route::get('language/{locale}', [LanguageController::class, 'switchLanguage'])->name('language.switch');

// Admin routes
Route::prefix('admin')->middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('admin.dashboard');
});