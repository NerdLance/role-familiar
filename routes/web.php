<?php

use App\Http\Controllers\AskPrototypeInterviewGuideController;
use App\Http\Controllers\CompletePrototypeQuestController;
use App\Http\Controllers\FamiliarController;
use App\Http\Controllers\FamiliarLabController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('familiar-lab', FamiliarLabController::class)->name('familiar-lab');
    Route::get('familiar', FamiliarController::class)->name('familiar');
    Route::post('familiar/{familiar}/quests', CompletePrototypeQuestController::class)->name('familiar.quests.complete');
    Route::post('familiar/interview-guide', AskPrototypeInterviewGuideController::class)
        ->middleware('throttle:10,1')
        ->name('familiar.interview-guide');
});

require __DIR__.'/settings.php';
