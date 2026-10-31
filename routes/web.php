<?php

use App\Enums\UserRole;
use App\Http\Controllers\Coordinator\VisitEvidenceController as CoordinatorVisitEvidenceController;
use App\Http\Controllers\Promoter\VisitEvidenceController;
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
])
    ->prefix('coordinator')
    ->name('coordinator.')
    ->group(function () {
        Route::get('/', function () {
            return view('coordinator.dashboard');
        })->name('dashboard');

        Route::get('/requests', function () {
            return view('coordinator.requests');
        })->name('requests.index');

        Route::get('/assignments', function () {
            return view('coordinator.assignments');
        })->name('assignments.index');

        Route::get('/assignments/{pharmacy}', function (int $pharmacy) {
            return view('coordinator.assignment-detail', [
                'pharmacyId' => $pharmacy,
            ]);
        })->whereNumber('pharmacy')
            ->name('assignments.show');

        Route::get('/schedules', function () {
            return view('coordinator.schedules');
        })->name('schedules.index');

        Route::get('/schedules/{schedule}', function (int $schedule) {
            return view('coordinator.schedule-detail', [
                'scheduleId' => $schedule,
            ]);
        })->whereNumber('schedule')
            ->name('schedules.show');

        Route::get('/visits', function () {
            return view('coordinator.visits');
        })->name('visits.index');

        Route::get('/visits/{visit}', function (int $visit) {
            return view('coordinator.visit-detail', [
                'visitId' => $visit,
            ]);
        })->whereNumber('visit')
            ->name('visits.show');

        Route::get('/visits/{visit}/evidence/{evidence}/download', [CoordinatorVisitEvidenceController::class, 'download'])
            ->whereNumber('visit')->whereUuid('evidence')->middleware('throttle:60,1')->name('visits.evidence.download');
        Route::get('/visits/{visit}/evidence/{evidence}/preview', [CoordinatorVisitEvidenceController::class, 'preview'])
            ->whereNumber('visit')->whereUuid('evidence')->middleware('throttle:120,1')->name('visits.evidence.preview');
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

    Route::get('/promoter/visits', function () {
        return view('promoter.visits');
    })->name('promoter.visits.index');

    Route::get('/promoter/visits/register', function () {
        $activity = request()->query('actividad');

        return view('promoter.visit-register', [
            'activityId' => is_string($activity) && ctype_digit($activity) ? (int) $activity : null,
        ]);
    })->name('promoter.visits.create');

    Route::get('/promoter/visits/history', function () {
        return view('promoter.visit-history');
    })->name('promoter.visits.history');

    Route::post('/promoter/visit-evidence/uploads', [VisitEvidenceController::class, 'upload'])
        ->middleware('throttle:20,1')->name('promoter.visits.evidence.upload');
    Route::get('/promoter/visits/{visit}/evidence/{evidence}/download', [VisitEvidenceController::class, 'download'])
        ->whereNumber('visit')->whereUuid('evidence')->name('promoter.visits.evidence.download');
    Route::get('/promoter/visits/{visit}/evidence/{evidence}/preview', [VisitEvidenceController::class, 'preview'])
        ->whereNumber('visit')->whereUuid('evidence')->name('promoter.visits.evidence.preview');

    Route::get('/promoter/visits/{visit}', function (int $visit) {
        return view('promoter.visit-detail', [
            'visitId' => $visit,
        ]);
    })->whereNumber('visit')
        ->name('promoter.visits.show');
});
