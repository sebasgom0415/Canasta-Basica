<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard público
Route::get('/', [DashboardController::class, 'index'])->name('home');

// API pública (JSON)
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/resumen',     [DashboardController::class, 'resumen'])->name('resumen');
    Route::get('/ipc',         [DashboardController::class, 'ipc'])->name('ipc');
    Route::get('/divisiones',  [DashboardController::class, 'divisiones'])->name('divisiones');
    Route::get('/evolucion',   [DashboardController::class, 'evolucion'])->name('evolucion');
    Route::get('/componentes', [DashboardController::class, 'componentes'])->name('componentes');
    Route::get('/canasta',     [DashboardController::class, 'canasta'])->name('canasta');
    Route::get('/subgrupos',   [DashboardController::class, 'subgrupos'])->name('subgrupos');
    Route::get('/salario',     [DashboardController::class, 'salario'])->name('salario');
    Route::get('/poder',       [DashboardController::class, 'poderAdquisitivo'])->name('poder');
});

// Panel de administración
Route::get('/admin',        [AdminController::class, 'index'])->name('admin.index');
Route::post('/admin/login', [AdminController::class, 'login'])->name('admin.login');
Route::get('/admin/logout', [AdminController::class, 'logout'])->name('admin.logout');

Route::middleware('admin.auth')->prefix('api/admin')->name('api.admin.')->group(function () {
    Route::get('/estado',          [AdminController::class, 'estado'])->name('estado');
    Route::post('/sincronizar',    [AdminController::class, 'sincronizar'])->name('sincronizar');
    Route::post('/canasta',        [AdminController::class, 'importarCanasta'])->name('canasta');
});
