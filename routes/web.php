<?php

use Illuminate\Support\Facades\Route;
use daacreators\CreatorsTicketing\Http\Controllers\TicketAttachmentController;
use daacreators\CreatorsTicketing\Http\Livewire\PublicSupportPage;

if (config('creators-ticketing.public_page_enabled')) {
    Route::get('/' . trim(config('creators-ticketing.public_path', 'support'), '/'), PublicSupportPage::class)
        ->middleware(['web', 'throttle:60,1'])
        ->name('creators-ticketing.support');
}

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/private/ticket-attachments/{ticketId}/{filename}',
        [TicketAttachmentController::class, 'show']
    )->name('creators-ticketing.attachment');
});

// ── Guest ticket tracking ─────────────────────────────────────────────────────
// Accessible without login — the token is the only "credential".
Route::middleware(['web', 'throttle:60,1'])->group(function () {
    Route::get('/tickets/track/{token}',
        \daacreators\CreatorsTicketing\Http\Livewire\GuestTicketView::class
    )->name('creators-ticketing.guest.track');
});
