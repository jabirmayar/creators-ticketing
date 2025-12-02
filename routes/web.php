<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::middleware(['web'])->group(function () {
    Route::get('/private/ticket-attachments/{ticketId}/{filename}', function ($ticketId, $filename) {
        if (! auth()->check()) {
            return redirect('/admin/login');
        }

        $ticket = \daacreators\CreatorsTicketing\Models\Ticket::findOrFail($ticketId);

        if (auth()->id() !== $ticket->user_id && auth()->id() !== $ticket->assignee_id) {
            abort(403, 'Unauthorized');
        }

        $path = "ticket-attachments/{$ticketId}/{$filename}";

        if (! Storage::disk('private')->exists($path)) {
            \Log::error('File not found', ['path' => $path, 'full_path' => Storage::disk('private')->path($path)]);
            abort(404, 'File not found');
        }

        return response()->file(
            Storage::disk('private')->path($path)
        );
    })->name('creators-ticketing.attachment');
});
