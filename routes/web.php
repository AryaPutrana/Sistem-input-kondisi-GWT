<?php

use App\Http\Controllers\Admin\MonitoringLocationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

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
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Admin & petugas: melihat, mengubah, dan menghapus data pemeriksaan
    Route::middleware('role:admin,petugas')->group(function () {
        Route::get('/monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
        Route::get('/monitoring/{monitoring}/edit', [MonitoringController::class, 'edit'])->name('monitoring.edit');
        Route::put('/monitoring/{monitoring}', [MonitoringController::class, 'update'])->name('monitoring.update');
        Route::delete('/monitoring/{monitoring}', [MonitoringController::class, 'destroy'])->name('monitoring.destroy');
    });

    // Hanya petugas: input pemeriksaan
    Route::middleware('role:petugas')->group(function () {
        Route::get('/monitoring/create', [MonitoringController::class, 'create'])->name('monitoring.create');
        Route::post('/monitoring', [MonitoringController::class, 'store'])->name('monitoring.store');
    });

    // Hanya admin
    Route::middleware('role:admin')->group(function () {
        Route::resource('lokasi', MonitoringLocationController::class)->except('show');
        Route::resource('pengguna', UserController::class)->except('show');
    });

    // Detail pemeriksaan — URL dapat dibagikan (shareable) sesuai PRD pasal 23.
    // Semua role login: petugas hanya dapat membuka data miliknya sendiri
    // (dicek pada MonitoringController::show -> 403 bila bukan miliknya),
    // admin dapat membuka semua data.
    Route::get('/monitoring/{monitoring}', [MonitoringController::class, 'show'])
        ->name('monitoring.show');
});