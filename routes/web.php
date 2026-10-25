<?php

use App\Http\Controllers\CmsEditorPreviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\GuestInvitationLifecycleController;
use App\Http\Controllers\InvitationGroupController;
use App\Http\Controllers\PersonalInvitationController;
use App\Http\Controllers\PersonalRsvpAccessController;
use App\Http\Controllers\PublicRsvpController;
use App\Http\Controllers\PublicWeddingController;
use App\Http\Controllers\WeddingAttendanceController;
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
Route::get('w/{slug}/media/{asset}', [PublicWeddingController::class, 'media'])->whereUuid('asset')->name('public.weddings.media');
Route::get('invitation-rsvp/csrf-token', [PublicRsvpController::class, 'csrf'])
    ->withoutMiddleware([HandleInertiaRequests::class, ShareErrorsFromSession::class])
    ->middleware('throttle:rsvp-setup')->name('public.invitations.csrf');
Route::post('invite/{token}/rsvp', fn () => abort(404))
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([HandleInertiaRequests::class, ShareErrorsFromSession::class])
    ->middleware('throttle:rsvp-submissions')->name('public.invitations.rsvp');
Route::get('invite/{token}', fn () => abort(404))
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([
        StartSession::class, ShareErrorsFromSession::class, HandleInertiaRequests::class,
        ValidateCsrfToken::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->middleware('throttle:invitation-access')->name('public.invitations.show');

Route::get('rsvp/{token}', [PersonalInvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, HandleInertiaRequests::class,
        ValidateCsrfToken::class, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->middleware('throttle:personal-rsvp-read')->name('public.personal-rsvp.show');
Route::post('rsvp/{token}/responses', [PersonalInvitationController::class, 'store'])
    ->where('token', '[a-f0-9]{64}')
    ->withoutMiddleware([HandleInertiaRequests::class, ShareErrorsFromSession::class])
    ->middleware('throttle:personal-rsvp-write')->name('public.personal-rsvp.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('weddings/create', [WeddingController::class, 'create'])->name('weddings.create');
    Route::post('weddings', [WeddingController::class, 'storeFirst'])->name('weddings.store');

    Route::scopeBindings()->prefix('accounts/{account}')->name('accounts.')->group(function () {
        Route::prefix('weddings/{wedding}/cms-preview')->name('weddings.cms-preview.')->group(function () {
            Route::get('draft', [CmsEditorPreviewController::class, 'draft'])->middleware('throttle:cms-editor-read')->name('draft');
            Route::put('draft', [CmsEditorPreviewController::class, 'save'])->middleware('throttle:cms-editor-save');
            Route::get('saved-preview', [CmsEditorPreviewController::class, 'savedPreview'])->middleware('throttle:cms-editor-read')->name('saved-preview');
            Route::get('/', [CmsEditorPreviewController::class, 'show'])->middleware('throttle:cms-editor-read')->name('show');
            Route::get('images', [CmsEditorPreviewController::class, 'images'])->middleware('throttle:cms-editor-read')->name('images');
            Route::get('images/{asset}', [CmsEditorPreviewController::class, 'image'])->whereUuid('asset')->middleware('throttle:cms-editor-read')->name('image');
            Route::post('images', [CmsEditorPreviewController::class, 'upload'])->middleware('throttle:cms-editor-upload')->name('upload');
            Route::post('validate', [CmsEditorPreviewController::class, 'validateContent'])->middleware('throttle:cms-editor-validation')->name('validate');
        });
        Route::get('weddings/create', [WeddingController::class, 'createInAccount'])->name('weddings.create');
        Route::post('weddings', [WeddingController::class, 'store'])->name('weddings.store');
        Route::get('weddings/{wedding}', [WeddingController::class, 'show'])->name('weddings.show');
        Route::get('weddings/{wedding}/preview', [WeddingController::class, 'preview'])->name('weddings.preview');
        Route::get('weddings/{wedding}/guests', [InvitationGroupController::class, 'index'])->name('weddings.guests.index');
        Route::get('weddings/{wedding}/attendance', [WeddingAttendanceController::class, 'index'])->name('weddings.attendance.index');
        Route::get('weddings/{wedding}/attendance/invitation-groups/{invitationGroup}', [WeddingAttendanceController::class, 'group'])->name('weddings.attendance.group');
        Route::post('weddings/{wedding}/invitation-groups', [InvitationGroupController::class, 'store'])->name('weddings.invitation-groups.store');
        Route::post('weddings/{wedding}/invitation-groups/{invitationGroup}/invitation-link/{action}', fn () => abort(404))
            ->whereIn('action', ['generate', 'copy', 'revoke', 'regenerate'])->name('weddings.invitation-links.update');
        Route::prefix('weddings/{wedding}/invitation-groups/{invitationGroup}/rsvp-access')->name('weddings.rsvp-access.')->middleware('throttle:personal-rsvp-owner')->group(function () {
            Route::get('/', [PersonalRsvpAccessController::class, 'show'])->name('show');
            Route::post('guests/{guest}/credential/{action}', [PersonalRsvpAccessController::class, 'credential'])->whereIn('action', ['generate', 'copy', 'revoke', 'regenerate'])->name('credential');
            Route::post('guests/{guest}/delegation', [PersonalRsvpAccessController::class, 'delegation'])->name('delegation');
            Route::get('guests/{guest}/invitation/{action}', [GuestInvitationLifecycleController::class, 'inspect'])->whereIn('action', ['withdraw', 'reinstate'])->name('invitation.inspect');
            Route::post('guests/{guest}/invitation/{action}', [GuestInvitationLifecycleController::class, 'change'])->whereIn('action', ['withdraw', 'reinstate'])->name('invitation.change');
        });
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
