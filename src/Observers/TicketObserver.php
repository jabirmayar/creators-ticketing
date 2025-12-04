<?php

namespace daacreators\CreatorsTicketing\Observers;

use App\Models\User;
use Illuminate\Support\Str;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketStatus;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Events\TicketAssigned;
use daacreators\CreatorsTicketing\Events\TicketStatusChanged;
use daacreators\CreatorsTicketing\Events\TicketPriorityChanged;
use daacreators\CreatorsTicketing\Events\TicketClosed;
use daacreators\CreatorsTicketing\Events\TicketDeleted;

class TicketObserver
{
    public function creating(Ticket $ticket): void
    {
        if (empty($ticket->ticket_uid)) {
            $ticket->ticket_uid = $this->generateTicketUid();
        }

        if (empty($ticket->ticket_status_id)) {
            $defaultStatus = TicketStatus::where('is_default_for_new', true)->first();
            if ($defaultStatus) {
                $ticket->ticket_status_id = $defaultStatus->id;
            }
        }

        if (empty($ticket->priority)) {
            $ticket->priority = TicketPriority::MEDIUM;
        }

        if (empty($ticket->last_activity_at)) {
            $ticket->last_activity_at = now();
        }

        $ticket->is_seen = false;
        $ticket->seen_by = null;
        $ticket->seen_at = null;
    }

    public function created(Ticket $ticket): void
    {
        $ticket->activities()->create([
            'user_id' => auth()->id(),
            'description' => 'Ticket was created',
        ]);

        event(new TicketCreated($ticket, auth()->user()));
    }

    public function updating(Ticket $ticket): void
    {
        if ($ticket->isDirty('assignee_id')) {
            $oldAssigneeId = $ticket->getOriginal('assignee_id');
            $newAssigneeId = $ticket->assignee_id;
            
            $oldAssignee = $oldAssigneeId ? User::find($oldAssigneeId) : null;
            $newAssignee = $newAssigneeId ? User::find($newAssigneeId) : null;

            $ticket->activities()->create([
                'user_id' => auth()->id(),
                'description' => 'Ticket was assigned',
                'old_value' => $oldAssignee?->name ?? 'Unassigned',
                'new_value' => $newAssignee?->name ?? 'Unassigned',
            ]);
        }

        if ($ticket->isDirty('ticket_status_id')) {
            $oldStatusId = $ticket->getOriginal('ticket_status_id');
            $newStatusId = $ticket->ticket_status_id;
            
            $oldStatus = TicketStatus::find($oldStatusId);
            $newStatus = TicketStatus::find($newStatusId);

            $ticket->activities()->create([
                'user_id' => auth()->id(),
                'description' => 'Status was changed',
                'old_value' => $oldStatus?->name,
                'new_value' => $newStatus?->name,
            ]);
        }

        if ($ticket->isDirty('priority')) {
            $oldPriority = $ticket->getOriginal('priority');
            $newPriority = $ticket->priority;
            
            if ($oldPriority instanceof TicketPriority) {
                $oldPriority = $oldPriority->getLabel();
            }
            if ($newPriority instanceof TicketPriority) {
                $newPriority = $newPriority->getLabel();
            }

            $ticket->activities()->create([
                'user_id' => auth()->id(),
                'description' => 'Priority was changed',
                'old_value' => $oldPriority,
                'new_value' => $newPriority,
            ]);
        }
    }

    public function updated(Ticket $ticket): void
    {
        if ($ticket->wasChanged('assignee_id')) {
            $oldAssigneeId = $ticket->getOriginal('assignee_id');
            $newAssigneeId = $ticket->assignee_id;

            event(new TicketAssigned(
                $ticket,
                $oldAssigneeId,
                $newAssigneeId,
                auth()->user()
            ));
        }

        if ($ticket->wasChanged('ticket_status_id')) {
            $oldStatusId = $ticket->getOriginal('ticket_status_id');
            $newStatusId = $ticket->ticket_status_id;
            
            $oldStatus = $oldStatusId ? TicketStatus::find($oldStatusId) : null;
            $newStatus = TicketStatus::find($newStatusId);

            event(new TicketStatusChanged(
                $ticket,
                $oldStatus,
                $newStatus,
                auth()->user()
            ));

            if ($newStatus && $newStatus->is_closing_status) {
                event(new TicketClosed($ticket, auth()->user()));
            }
        }

        if ($ticket->wasChanged('priority')) {
            $oldPriority = TicketPriority::from($ticket->getRawOriginal('priority'));
            $newPriority = $ticket->priority;

            event(new TicketPriorityChanged(
                $ticket,
                $oldPriority,
                $newPriority,
                auth()->user()
            ));
        }
    }

    public function deleting(Ticket $ticket): void
    {
        event(new TicketDeleted(
            $ticket->id,
            $ticket->ticket_uid,
            auth()->user()
        ));
    }

    public function replying(Ticket $ticket, $reply): void
    {
        $activityType = $reply->is_internal_note ? 'Internal note added' : 'Reply sent';
        
        $ticket->activities()->create([
            'user_id' => $reply->user_id,
            'description' => $activityType,
            'new_value' => substr(strip_tags($reply->content), 0, 100) . '...',
        ]);

        $ticket->last_activity_at = now();

        if ($reply->user_id == $ticket->user_id) {
            $ticket->is_seen = false;
            $ticket->seen_by = null;
            $ticket->seen_at = null;
        }

        $ticket->saveQuietly();

        if ($reply instanceof \daacreators\CreatorsTicketing\Models\TicketReply) {
            $reply->is_seen = false;
            $reply->seen_by = null;
            $reply->seen_at = null;
            $reply->saveQuietly();
        }
    }

    protected function generateTicketUid(): string
    {
        $prefix = config('creators-ticketing.ticket_prefix', 'TKT');
        $date = now()->format(config('creators-ticketing.ticket_date_format', 'ymd'));
        $random = strtoupper(Str::random(config('creators-ticketing.ticket_random_length', 6)));

        $format = config('creators-ticketing.ticket_format', '{PREFIX}-{DATE}-{RAND}');
        $uid = str_replace(['{PREFIX}', '{DATE}', '{RAND}'], [$prefix, $date, $random], $format);

        if (Ticket::where('ticket_uid', $uid)->exists()) {
            return $this->generateTicketUid();
        }

        return $uid;
    }

}