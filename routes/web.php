<?php

use Illuminate\Support\Facades\Route;
use daacreators\CreatorsTicketing\Http\Controllers\TicketAttachmentController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/private/ticket-attachments/{ticketId}/{filename}', 
        [TicketAttachmentController::class, 'show']
    )->name('creators-ticketing.attachment');
});