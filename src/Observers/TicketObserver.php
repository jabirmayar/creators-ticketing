<?php

namespace Daacreators\CreatorsTicketing\Observers;

use App\Models\User;
use Illuminate\Support\Str;
use Daacreators\CreatorsTicketing\Models\Ticket;
use Daacreators\CreatorsTicketing\Models\TicketStatus;
use Daacreators\CreatorsTicketing\Enums\TicketPriority;

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
    }

    public function created(Ticket $ticket): void
    {
        $ticket->activities()->create([
            'user_id' => auth()->id(),
            'description' => 'Ticket was created',
        ]);
        // mark ticket as seen by creator
        if (auth()->check()) {
            $ticket->markSeenBy(auth()->id());
        }
    }

    public function updating(Ticket $ticket): void
    {
        if ($ticket->isDirty('assignee_id')) {
            $oldAssigneeId = $ticket->getOriginal('assignee_id');
            $newAssigneeId = $ticket->assignee_id;
            
            if ($oldAssigneeId !== $newAssigneeId) {
                $oldAssignee = User::find($oldAssigneeId);
                $newAssignee = User::find($newAssigneeId);

                $ticket->activities()->create([
                    'user_id' => auth()->id(),
                    'description' => 'Ticket was assigned',
                    'old_value' => $oldAssignee?->name ?? 'Unassigned',
                    'new_value' => $newAssignee?->name ?? 'Unassigned',
                ]);
            }
        }

        if ($ticket->isDirty('ticket_status_id')) {
            $oldStatusId = $ticket->getOriginal('ticket_status_id');
            $newStatusId = $ticket->ticket_status_id;
            
            if ($oldStatusId !== $newStatusId) {
                $oldStatus = TicketStatus::find($oldStatusId);
                $newStatus = TicketStatus::find($newStatusId);

                $ticket->activities()->create([
                    'user_id' => auth()->id(),
                    'description' => 'Status was changed',
                    'old_value' => $oldStatus?->name,
                    'new_value' => $newStatus?->name,
                ]);
            }
        }

        if ($ticket->isDirty('priority')) {
            $oldPriority = $ticket->getOriginal('priority');
            $newPriority = $ticket->priority;
            
            if ($oldPriority !== $newPriority) {
                $ticket->activities()->create([
                    'user_id' => auth()->id(),
                    'description' => 'Priority was changed',
                    'old_value' => $oldPriority,
                    'new_value' => $newPriority,
                ]);
            }
        }
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
        $ticket->saveQuietly();
        // ensure reply stored as unseen for other users and ticket is flagged unseen
        if ($reply instanceof \Daacreators\CreatorsTicketing\Models\TicketReply) {
            $reply->is_seen = false;
            $reply->seen_by = null;
            $reply->seen_at = null;
            $reply->saveQuietly();
        }
        $ticket->markUnseen();
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