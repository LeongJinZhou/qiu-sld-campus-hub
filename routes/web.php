<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubQrController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicProfileController;
use App\Models\Club;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/', fn () => Inertia::render('Auth/Login', ['devLogin' => app()->isLocal()]))->name('login');
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:10,1');

    if (app()->isLocal()) {
        Route::get('/dev/login-as', [DevLoginController::class, 'index'])->name('dev.login');
        Route::post('/dev/login-as', [DevLoginController::class, 'store'])->middleware('throttle:30,1');
    }
});

Route::get('/not-in-records', fn () => Inertia::render('Auth/NotFoundInRecords'))->name('not-in-records');
Route::get('/p/{token}', PublicProfileController::class)->name('profile.public');

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');
    Route::get('/c/{token}', ClubQrController::class)->name('clubs.qr');

    Route::get('/me', [ProfileController::class, 'show'])->name('profile.me');
    Route::post('/me', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('admin')->name('admin.')->middleware('can:manage,'.Club::class)->group(function () {
        Route::resource('clubs', Admin\ClubController::class)->except(['show', 'destroy']);
        Route::post('clubs/{club}/committee', [Admin\ClubCommitteeController::class, 'assign'])->name('clubs.committee.assign');
        Route::post('clubs/{club}/advisors', [Admin\ClubCommitteeController::class, 'addAdvisor'])->name('clubs.advisors.store');
        Route::delete('clubs/{club}/assignments/{assignment}', [Admin\ClubCommitteeController::class, 'remove'])->name('clubs.assignments.destroy');
        Route::post('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'store'])->name('clubs.transfer');
        Route::delete('clubs/{club}/transfer', [Admin\ClubTransferController::class, 'destroy'])->name('clubs.take-back');

        Route::get('staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::post('staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::put('staff/{staff}', [Admin\StaffController::class, 'update'])->name('staff.update');

        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings/{section}', [Admin\SettingsController::class, 'update'])->name('settings.update');

        Route::middleware('role:superadmin')->group(function () {
            Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{user}', [Admin\RoleController::class, 'update'])->name('roles.update');
            Route::get('audit', [Admin\AuditController::class, 'index'])->name('audit.index');
            Route::get('sync', [Admin\SyncController::class, 'index'])->name('sync.index');
            Route::post('sync', [Admin\SyncController::class, 'store'])->name('sync.store');
        });
    });
});
