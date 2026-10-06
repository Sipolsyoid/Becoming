<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HabitCompletionController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/photo-checks/{completion}', [HabitCompletionController::class, 'show'])->name('checks.show');
    Route::post('/photo-checks/{completion}/retry', [HabitCompletionController::class, 'retry'])->name('checks.retry');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('/dashboard', function () {
        return redirect()->route('dashboard');
    });

    Route::get('/progress', ProgressController::class)->name('progress');

    Route::get('/habits', [HabitController::class, 'index'])->name('habits.index');
    Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
    Route::patch('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
    Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
    Route::post('/habits/{habit}/complete-with-photo', [HabitCompletionController::class, 'submitPhoto'])
        ->name('habits.complete');

    Route::get('/history', HistoryController::class)->name('history');
});

require __DIR__.'/auth.php';
