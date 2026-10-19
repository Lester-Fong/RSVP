<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\InvitationGroupController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\PublicRsvpController;
use App\Http\Controllers\PublicWeddingController;
use App\Http\Controllers\WeddingController;
use App\Http\Controllers\WeddingInvitationController;
use App\Http\Controllers\WeddingPublicationController;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('w/{slug}', [PublicWeddingController::class, 'show'])->name('public.weddings.show');
Route::get('w/{slug}/photo/{photo}', [PublicWeddingController::class, 'photo'])->whereUuid('photo')->name('public.weddings.photo');
Route::get('invitation-rsvp/csrf-token', [PublicRsvpController::class, 'csrf'])
    ->withoutMiddleware([HandleInertiaRequests::class, ShareErrorsFromSession::class])
    ->middleware('throttle:rsvp-setup')->name('public.invitations.csrf');
Route::post('invite/{token}/rsvp', [PublicRsvpController::class, 'store'])
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([HandleInertiaRequests::class, ShareErrorsFromSession::class])
    ->middleware('throttle:rsvp-submissions')->name('public.invitations.rsvp');
Route::get('invite/{token}', [PublicInvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([
        StartSession::class, ShareErrorsFromSession::class, HandleInertiaRequests::class,
        ValidateCsrfToken::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->middleware('throttle:invitation-access')->name('public.invitations.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('weddings/create', [WeddingController::class, 'create'])->name('weddings.create');
    Route::post('weddings', [WeddingController::class, 'storeFirst'])->name('weddings.store');

    Route::scopeBindings()->prefix('accounts/{account}')->name('accounts.')->group(function () {
        Route::get('weddings/create', [WeddingController::class, 'createInAccount'])->name('weddings.create');
        Route::post('weddings', [WeddingController::class, 'store'])->name('weddings.store');
        Route::get('weddings/{wedding}', [WeddingController::class, 'show'])->name('weddings.show');
        Route::get('weddings/{wedding}/preview', [WeddingController::class, 'preview'])->name('weddings.preview');
        Route::get('weddings/{wedding}/guests', [InvitationGroupController::class, 'index'])->name('weddings.guests.index');
        Route::post('weddings/{wedding}/invitation-groups', [InvitationGroupController::class, 'store'])->name('weddings.invitation-groups.store');
        Route::post('weddings/{wedding}/invitation-groups/{invitationGroup}/invitation-link/{action}', [InvitationLinkController::class, 'update'])
            ->whereIn('action', ['generate', 'copy', 'revoke', 'regenerate'])->name('weddings.invitation-links.update');
        Route::patch('weddings/{wedding}/invitation-groups/{invitationGroup}', [InvitationGroupController::class, 'update'])->name('weddings.invitation-groups.update');
        Route::delete('weddings/{wedding}/invitation-groups/{invitationGroup}', [InvitationGroupController::class, 'destroy'])->name('weddings.invitation-groups.destroy');
        Route::post('weddings/{wedding}/invitation-groups/{invitationGroup}/guests', [GuestController::class, 'store'])->name('weddings.guests.store');
        Route::patch('weddings/{wedding}/invitation-groups/{invitationGroup}/guests/{guest}', [GuestController::class, 'update'])->name('weddings.guests.update');
        Route::delete('weddings/{wedding}/invitation-groups/{invitationGroup}/guests/{guest}', [GuestController::class, 'destroy'])->name('weddings.guests.destroy');
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
