<?php

use App\Http\Controllers\AuthControllers\AuthenticatedSessionController;
use App\Http\Controllers\AuthControllers\PasswordController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:login','guest'])->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
