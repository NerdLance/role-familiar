<?php

use App\Http\Controllers\ApplyRoleAnalysisProposalController;
use App\Http\Controllers\ArchiveTargetRoleController;
use App\Http\Controllers\AskPrototypeInterviewGuideController;
use App\Http\Controllers\CompletePrototypeQuestController;
use App\Http\Controllers\ConfirmTargetRoleController;
use App\Http\Controllers\FamiliarController;
use App\Http\Controllers\FamiliarLabController;
use App\Http\Controllers\JobListingTargetRoleController;
use App\Http\Controllers\RequestJobListingAnalysisController;
use App\Http\Controllers\RestoreTargetRoleController;
use App\Http\Controllers\RetryJobListingAnalysisController;
use App\Http\Controllers\TargetRoleController;
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

    Route::post('target-roles/{targetRole}/confirm', ConfirmTargetRoleController::class)->name('target-roles.confirm');
    Route::post('target-roles/{targetRole}/archive', ArchiveTargetRoleController::class)->name('target-roles.archive');
    Route::post('target-roles/{targetRole}/restore', RestoreTargetRoleController::class)
        ->withTrashed()
        ->name('target-roles.restore');
    Route::post('target-roles/{targetRole}/analysis', RequestJobListingAnalysisController::class)
        ->middleware('throttle:target-role-analysis')
        ->name('target-roles.analysis.store');
    Route::post('target-roles/{targetRole}/analysis/retry', RetryJobListingAnalysisController::class)
        ->middleware('throttle:target-role-analysis')
        ->name('target-roles.analysis.retry');
    Route::put('target-roles/{targetRole}/analyses/{analysis}/review', ApplyRoleAnalysisProposalController::class)
        ->scopeBindings()
        ->name('target-roles.analysis.review.update');
    Route::get('target-roles/job-listing/create', [JobListingTargetRoleController::class, 'create'])
        ->name('target-roles.job-listing.create');
    Route::post('target-roles/job-listing', [JobListingTargetRoleController::class, 'store'])
        ->middleware('throttle:target-role-analysis')
        ->name('target-roles.job-listing.store');
    Route::resource('target-roles', TargetRoleController::class)
        ->parameters(['target-roles' => 'targetRole']);
});

require __DIR__.'/settings.php';
