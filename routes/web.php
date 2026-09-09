<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return match (auth()->user()->role) {
        UserRole::ADMIN => redirect()->route('admin.dashboard'),
        UserRole::COORDINATOR => redirect()->route('coordinator.dashboard'),
        UserRole::PROMOTER => redirect()->route('promoter.dashboard'),
    };
})->name('home');

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return 'Panel de Administrador';
    })->name('admin.dashboard');
});

Route::middleware(['auth', 'role:coordinator'])->group(function () {
    Route::get('/coordinator', function () {
        return 'Panel de Coordinadora';
    })->name('coordinator.dashboard');
});

Route::middleware(['auth', 'role:promoter'])->group(function () {
    Route::get('/promoter', function () {
        return 'Panel de Promotora';
    })->name('promoter.dashboard');
});
