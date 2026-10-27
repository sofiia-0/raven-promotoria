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

Route::middleware([
    'auth',
    'role:admin',
])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::get('/users', function () {
            return view('admin.users');
        })->name('users.index');
    });

Route::middleware([
    'auth',
    'role:coordinator',
])->group(function () {
    Route::get('/coordinator', function () {
        return 'Panel de Coordinadora';
    })->name('coordinator.dashboard');
});

Route::middleware([
    'auth',
    'role:promoter',
])->group(function () {
    Route::get('/promoter', function () {
        return view('promoter.dashboard');
    })->name('promoter.dashboard');

    Route::get('/promoter/pharmacies', function () {
        return view('promoter.pharmacies');
    })->name('promoter.pharmacies.index');

    Route::get('/promoter/pharmacies/{pharmacy}', function (int $pharmacy) {
        return view('promoter.pharmacy-detail', [
            'pharmacyId' => $pharmacy,
        ]);
    })->whereNumber('pharmacy')
      ->name('promoter.pharmacies.show');

    Route::get('/promoter/requests', function () {
        return view('promoter.requests');
    })->name('promoter.requests.index');

    Route::get('/promoter/planning', function () {
        return view('promoter.planning');
    })->name('promoter.planning.index');

    Route::get('/promoter/visits/history', function () {
        return view('promoter.visit-history');
    })->name('promoter.visits.history');

    Route::get('/promoter/visits/{visit}', function (int $visit) {
        return view('promoter.visit-detail', [
            'visitId' => $visit,
        ]);
    })->whereNumber('visit')
        ->name('promoter.visits.show');
});
