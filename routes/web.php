<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ContestantLoginController;
use App\Http\Controllers\Contestant\BallotController;
use App\Http\Controllers\PublicSite\CarController;
use App\Http\Controllers\PublicSite\GalleryController;
use App\Http\Controllers\PublicSite\ResultsController;
use Illuminate\Support\Facades\Route;

// Public: no login required.
Route::get('/', [GalleryController::class, 'index'])->name('gallery');
Route::get('/tallies', [GalleryController::class, 'tallies'])->name('tallies');
Route::get('/cars/{entryNumber}', [CarController::class, 'show'])->whereNumber('entryNumber')->name('cars.show');
Route::get('/results', [ResultsController::class, 'show'])->name('results');

// Contestant sign-in with a login code.
Route::middleware('guest')->group(function () {
    Route::get('/login', [ContestantLoginController::class, 'show'])->name('login');
    Route::post('/login', [ContestantLoginController::class, 'store'])->middleware('throttle:contestant-login')->name('login.store');
});
Route::post('/logout', [ContestantLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'contestant'])->group(function () {
    Route::get('/ballot', [BallotController::class, 'index'])->name('ballot.index');
    Route::post('/ballot/review', [BallotController::class, 'review'])->middleware('throttle:ballot')->name('ballot.review');
    Route::post('/ballot', [BallotController::class, 'store'])->middleware('throttle:ballot')->name('ballot.store');
});

// Admin: separate password sign-in.
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminLoginController::class, 'show'])->name('login');
        Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/event', [Admin\EventController::class, 'edit'])->name('event.edit');
        Route::put('/event', [Admin\EventController::class, 'update'])->name('event.update');
        Route::post('/event/open', [Admin\EventController::class, 'open'])->name('event.open');
        Route::post('/event/close', [Admin\EventController::class, 'close'])->name('event.close');

        Route::get('/contestants/ballots', [Admin\BallotPrintController::class, 'all'])->name('contestants.ballots');
        Route::resource('contestants', Admin\ContestantController::class)->except(['destroy'])->parameters(['contestants' => 'participant']);
        Route::get('/contestants/{participant}/ballot', [Admin\BallotPrintController::class, 'show'])->name('contestants.ballot');
        Route::post('/contestants/{participant}/rotate-code', [Admin\ContestantController::class, 'rotateCode'])->name('contestants.rotate-code');
        Route::put('/contestants/{participant}/allowance', [Admin\ContestantController::class, 'updateAllowance'])->name('contestants.allowance');

        Route::resource('cars', Admin\CarController::class)->except(['show']);
        Route::resource('categories', Admin\CategoryController::class)->except(['show']);

        Route::get('/paper', [Admin\PaperBallotController::class, 'index'])->name('paper.index');
        // POST so a typed login code never lands in URLs or server access logs.
        Route::post('/paper/find', [Admin\PaperBallotController::class, 'find'])->name('paper.find');
        Route::get('/paper/{participant}', [Admin\PaperBallotController::class, 'create'])->name('paper.create');
        Route::post('/paper/{participant}/preview', [Admin\PaperBallotController::class, 'preview'])->name('paper.preview');
        Route::post('/paper/{participant}', [Admin\PaperBallotController::class, 'store'])->name('paper.store');

        // Votes are immutable: list and show only. Creation goes through the ballot flows above.
        Route::get('/votes', [Admin\VoteController::class, 'index'])->name('votes.index');
        Route::get('/votes/{vote}', [Admin\VoteController::class, 'show'])->name('votes.show');

        Route::get('/results', [Admin\ResultController::class, 'show'])->name('results.show');
        Route::get('/results/print', [Admin\ResultController::class, 'print'])->name('results.print');
        Route::post('/results/tiebreak', [Admin\ResultController::class, 'tiebreak'])->name('results.tiebreak');
        Route::post('/results/finalize', [Admin\ResultController::class, 'finalize'])->name('results.finalize');

        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [Admin\ReportController::class, 'show'])
            ->whereIn('report', ['votes-by-car', 'votes-by-category', 'entries-by-class', 'awards', 'reconciliation'])
            ->name('reports.show');
    });
});
