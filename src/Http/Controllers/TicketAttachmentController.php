<?php

namespace daacreators\CreatorsTicketing\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Facades\Filament;
use daacreators\CreatorsTicketing\Models\Ticket;

class TicketAttachmentController extends Controller
{
    public function show($ticketId, $filename)
    {
        $user = $this->resolveAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedAccess($ticketId);
        }

        $ticket = Ticket::findOrFail($ticketId);

        if (!$this->userHasAccessToTicket($user, $ticket)) {
            return $this->unauthorizedAccess($ticketId, $user);
        }

        $path = "ticket-attachments/{$ticketId}/{$filename}";

        if (!$this->fileExists($path)) {
            return $this->fileNotFound($path);
        }

        return $this->streamFile($path);
    }

    private function resolveAuthenticatedUser()
    {
        $user = auth()->user();

        if (!$user && class_exists(Filament::class)) {
            $user = Filament::auth()->user();
        }

        return $user;
    }

    private function userHasAccessToTicket($user, $ticket)
    {
        if ($user->getKey() == $ticket->user_id || $user->getKey() == $ticket->assignee_id) {
            return true;
        }

        if ($this->userIsSuperAdmin($user)) {
            return true;
        }

        return $this->userIsDepartmentAgent($user, $ticket->department_id);
    }

    private function userIsSuperAdmin($user)
    {
        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);

        return in_array($user->{$field} ?? null, $allowed, true);
    }

    private function userIsDepartmentAgent($user, $departmentId)
    {
        return DB::table(config('creators-ticketing.table_prefix') . 'department_users')
            ->where('user_id', $user->getKey())
            ->where('department_id', $departmentId)
            ->exists();
    }

    private function unauthorizedAccess($ticketId, $user = null)
    {
        if ($user) {
            Log::warning('Unauthorized attachment access', [
                'ticketId' => $ticketId,
                'userId' => $user->getKey()
            ]);
            abort(403, 'Unauthorized');
        }

        Log::warning('Unauthorized attachment access - no user', ['ticketId' => $ticketId]);
        return redirect('/admin/login');
    }

    private function fileExists($path)
    {
        return Storage::disk('private')->exists($path);
    }

    private function fileNotFound($path)
    {
        Log::error('File not found in storage', [
            'path' => $path,
            'disk' => 'private'
        ]);
        abort(404, 'File not found');
    }

    private function streamFile($path)
    {
        $disk = Storage::disk('private');
        $filePath = $disk->path($path);
        $mimeType = $disk->mimeType($path);

        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
