<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicWeddingController;
use App\Http\Controllers\WeddingController;
use App\Http\Controllers\WeddingInvitationController;
use App\Http\Controllers\WeddingPublicationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('w/{slug}', [PublicWeddingController::class, 'show'])->name('public.weddings.show');
Route::get('w/{slug}/photo/{photo}', [PublicWeddingController::class, 'photo'])->whereUuid('photo')->name('public.weddings.photo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('weddings/create', [WeddingController::class, 'create'])->name('weddings.create');
    Route::post('weddings', [WeddingController::class, 'storeFirst'])->name('weddings.store');

    Route::scopeBindings()->prefix('accounts/{account}')->name('accounts.')->group(function () {
        Route::get('weddings/create', [WeddingController::class, 'createInAccount'])->name('weddings.create');
        Route::post('weddings', [WeddingController::class, 'store'])->name('weddings.store');
        Route::get('weddings/{wedding}', [WeddingController::class, 'show'])->name('weddings.show');
        Route::get('weddings/{wedding}/preview', [WeddingController::class, 'preview'])->name('weddings.preview');
        Route::post('weddings/{wedding}/publish', [WeddingPublicationController::class, 'publish'])->name('weddings.publish');
        Route::post('weddings/{wedding}/unpublish', [WeddingPublicationController::class, 'unpublish'])->name('weddings.unpublish');
        Route::get('weddings/{wedding}/invitation/edit', [WeddingInvitationController::class, 'edit'])->name('weddings.invitation.edit');
        Route::put('weddings/{wedding}/invitation', [WeddingInvitationController::class, 'update'])->name('weddings.invitation.update');
        Route::get('weddings/{wedding}/invitation/hero-photo/{photo}', [WeddingInvitationController::class, 'heroPhoto'])->whereUuid('photo')->name('weddings.invitation.hero-photo');
        Route::get('weddings/{wedding}/edit', [WeddingController::class, 'edit'])->name('weddings.edit');
        Route::put('weddings/{wedding}', [WeddingController::class, 'update'])->name('weddings.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
