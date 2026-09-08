<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
