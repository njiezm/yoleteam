<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BoatConfigurationController;
use App\Http\Controllers\BoatController;
use App\Http\Controllers\CrewPlanController;
use App\Http\Controllers\CrewPlanValidationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OutingCalendarController;
use App\Http\Controllers\OutingController;
use App\Http\Controllers\OutingResultController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RaceController;
use App\Http\Controllers\RaceResultController;
use App\Http\Controllers\RaceStageController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\SuperAdmin\AssociationController as SuperAdminAssociationController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe-oublie', [PasswordResetController::class, 'store'])->middleware('throttle:login')->name('password.email');
    Route::get('/reinitialisation/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reinitialisation', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::view('/plus', 'more')->name('more');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/mot-de-passe', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Sorties, appel et plans d'équipage (admin + patrons).
    Route::get('/sorties/hors-ligne', [OutingController::class, 'offline'])->name('outings.offline');
    Route::get('/sorties/par-identifiant/{uuid}', [OutingController::class, 'byUuid'])->whereUuid('uuid')->name('outings.by-uuid');
    Route::get('/sorties/calendrier', OutingCalendarController::class)->name('outings.calendar');
    Route::resource('sorties', OutingController::class)
        ->parameters(['sorties' => 'outing'])
        ->names('outings');
    Route::put('/sorties/{outing}/resultats', [OutingResultController::class, 'update'])->name('outings.results.update');
    Route::get('/statistiques', [StatisticsController::class, 'index'])->name('statistics.index');

    Route::get('/appel', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('/sorties/{outing}/appel', [AttendanceController::class, 'edit'])->name('attendance.edit');
    Route::put('/sorties/{outing}/appel', [AttendanceController::class, 'update'])->name('attendance.update');

    Route::get('/equipage', [CrewPlanController::class, 'today'])->name('crew-plans.today');
    Route::scopeBindings()->group(function () {
        Route::post('/sorties/{outing}/equipages', [CrewPlanController::class, 'store'])->name('crew-plans.store');
        Route::get('/sorties/{outing}/equipages/{crewPlan}', [CrewPlanController::class, 'show'])->name('crew-plans.show');
        Route::get('/sorties/{outing}/equipages/{crewPlan}/modifier', [CrewPlanController::class, 'edit'])->name('crew-plans.edit');
        Route::put('/sorties/{outing}/equipages/{crewPlan}', [CrewPlanController::class, 'update'])->name('crew-plans.update');
        Route::delete('/sorties/{outing}/equipages/{crewPlan}', [CrewPlanController::class, 'destroy'])->name('crew-plans.destroy');
        Route::post('/sorties/{outing}/equipages/{crewPlan}/validation', [CrewPlanValidationController::class, 'store'])->name('crew-plans.validation.store');
        Route::delete('/sorties/{outing}/equipages/{crewPlan}/validation', [CrewPlanValidationController::class, 'destroy'])->name('crew-plans.validation.destroy');
    });

    // Mode hors ligne : rejeu des opérations enregistrées sur l'appareil (appel et plans d'équipage).
    Route::get('/synchronisation', [SyncController::class, 'index'])->name('sync.index');
    Route::get('/synchronisation/jeton', [SyncController::class, 'token'])->name('sync.token');
    Route::post('/synchronisation', [SyncController::class, 'store'])->name('sync.store');
    Route::post('/synchronisation/{syncOperation}/resolution', [SyncController::class, 'resolve'])->name('sync.resolve');

    Route::get('/presences/statistiques', [HistoryController::class, 'index'])->name('attendance.stats');
    Route::redirect('/historique', '/presences/statistiques')->name('history.index');
    Route::get('/historique/export', [HistoryController::class, 'export'])->name('history.export');

    // Consultation for everyone, management for admin / bureau (see Gate "manage").
    Route::get('/membres/export', [MemberController::class, 'export'])->name('members.export');
    Route::get('/membres/imprimer', [MemberController::class, 'print'])->name('members.print');
    Route::resource('membres', MemberController::class)
        ->parameters(['membres' => 'member'])
        ->names('members')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:manage');

    Route::resource('yoles', BoatController::class)
        ->parameters(['yoles' => 'boat'])
        ->names('boats')
        ->except(['edit'])
        ->middlewareFor(['create', 'store', 'update', 'destroy'], 'can:manage');

    Route::resource('regates', RaceController::class)
        ->parameters(['regates' => 'race'])
        ->names('races')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:manage');

    Route::middleware('can:manage')->scopeBindings()->group(function () {
        Route::post('/yoles/{boat}/configurations', [BoatConfigurationController::class, 'store'])->name('boats.configurations.store');
        Route::put('/yoles/{boat}/configurations/{configuration}', [BoatConfigurationController::class, 'update'])->name('boats.configurations.update');
        Route::delete('/yoles/{boat}/configurations/{configuration}', [BoatConfigurationController::class, 'destroy'])->name('boats.configurations.destroy');

        Route::post('/regates/{race}/etapes', [RaceStageController::class, 'store'])->name('races.stages.store');
        Route::put('/regates/{race}/etapes/{stage}', [RaceStageController::class, 'update'])->name('races.stages.update');
        Route::delete('/regates/{race}/etapes/{stage}', [RaceStageController::class, 'destroy'])->name('races.stages.destroy');
        Route::put('/regates/{race}/etapes/{stage}/resultats', [RaceResultController::class, 'update'])->name('races.stages.results.update');

        Route::get('/parametres', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/parametres', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('/parametres/utilisateurs', [UserController::class, 'store'])->name('users.store');
        Route::delete('/parametres/utilisateurs/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Plateforme : toutes les associations et tous les comptes (super admin uniquement).
    Route::middleware('can:super-admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/', SuperAdminDashboardController::class)->name('dashboard');

        Route::resource('associations', SuperAdminAssociationController::class)->except(['show']);
        Route::post('/associations/{association}/ouvrir', [SuperAdminAssociationController::class, 'switch'])->name('associations.switch');

        Route::resource('utilisateurs', SuperAdminUserController::class)
            ->parameters(['utilisateurs' => 'user'])
            ->names('users')
            ->except(['show']);
        Route::post('/utilisateurs/{user}/activation', [SuperAdminUserController::class, 'toggle'])->name('users.toggle');
        Route::post('/utilisateurs/{user}/reinitialisation', [SuperAdminUserController::class, 'sendResetLink'])->name('users.reset-link');
    });
});
