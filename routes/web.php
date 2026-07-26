<?php

use App\Livewire\Informes\GestantesCorreccionUploader;
use App\Services\Gestantes\GestantesAutoCorrector;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->prefix('informes')
    ->name('informes.')
    ->group(function () {
        Route::view(
            '/resolucion-202',
            'informes.resolucion-202'
        )->name('resolucion-202');

        Route::view(
            '/gestantes',
            'informes.gestantes'
        )->name('gestantes');

        Route::view(
            '/gestantes/validar-excel',
            'informes.gestantes-validar'
        )->name('gestantes.validar-excel');

        Route::get(
            '/gestantes/correccion-sigires',
            GestantesCorreccionUploader::class
        )->name('gestantes.correccion-sigires');
    });

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')
        ->name('settings.profile');

    Volt::route('settings/password', 'settings.password')
        ->name('settings.password');

    Volt::route('settings/appearance', 'settings.appearance')
        ->name('settings.appearance');
});

Route::get('/test-gestantes-corrector', function (
    GestantesAutoCorrector $corrector
) {
    $archivo = storage_path(
        'app/private/pruebas/gestantes.xlsx'
    );

    return $corrector->correct($archivo);
})->middleware(['auth', 'verified']);

require __DIR__.'/auth.php';