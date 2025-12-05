<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Filament\Facades\Filament;
use daacreators\CreatorsTicketing\Models\Ticket;

Route::middleware(['web'])->group(function () {
    Route::get('/private/ticket-attachments/{ticketId}/{filename}', function ($ticketId, $filename) {

        // 1. Resolve User (Standard Auth or Filament Auth)
        $user = auth()->user();
        if (!$user && class_exists(Filament::class)) {
            $user = Filament::auth()->user();
        }

        if (!$user) {
            return redirect('/admin/login');
        }

        $ticket = Ticket::findOrFail($ticketId);

        $hasAccess = false;

        // 2. Check: Requester or Assignee
        if ($user->getKey() == $ticket->user_id || $user->getKey() == $ticket->assignee_id) {
            $hasAccess = true;
        }

        // 3. Check: Super Admin (Config based)
        if (!$hasAccess) {
            $field = config('creators-ticketing.navigation_visibility.field', 'email');
            $allowed = config('creators-ticketing.navigation_visibility.allowed', []);
            $isAdmin = in_array($user->{$field} ?? null, $allowed, true);

            if ($isAdmin) {
                $hasAccess = true;
            }
        }

        // 4. Check: Department Agent (Database based)
        if (!$hasAccess) {
            $pivotUserColumn = 'user_id';

            $isDepartmentAgent = DB::table(config('creators-ticketing.table_prefix') . 'department_users')
                ->where($pivotUserColumn, $user->getKey())
                ->where('department_id', $ticket->department_id)
                ->exists();

            if ($isDepartmentAgent) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            abort(403, 'Unauthorized');
        }

        $path = "ticket-attachments/{$ticketId}/{$filename}";

        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'File not found');
        }

        return response()->file(
            Storage::disk('private')->path($path)
        );
    })->name('creators-ticketing.attachment');
});