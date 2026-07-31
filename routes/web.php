<?php

use App\Http\Controllers\FamiliarLabController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('familiar-lab', FamiliarLabController::class)->name('familiar-lab');
});

require __DIR__.'/settings.php';
