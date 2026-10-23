<?php

namespace daacreators\CreatorsTicketing\Listeners;

use daacreators\CreatorsTicketing\Events\TicketCreated;
use daacreators\CreatorsTicketing\Events\TicketReplyAdded;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Notifications\CustomerTicketNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

class SendCustomerTicketEmail
{
    public function created(TicketCreated $event): void
    {
        $this->send($event->ticket);
    }

    public function replied(TicketReplyAdded $event): void
    {
        $reply = $event->reply;
        if ($reply->is_internal_note || $reply->user_id === null
            || (string) $reply->user_id === (string) $event->ticket->user_id) {
            return;
        }

        $this->send($event->ticket, $reply->content);
    }

    protected function send(Ticket $ticket, ?string $reply = null): void
    {
        if (! config('creators-ticketing.customer_emails_enabled')) {
            return;
        }

        $email = $ticket->isGuestTicket() ? $ticket->guest_email : $ticket->requester?->email;
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $url = $ticket->isGuestTicket()
            ? route('creators-ticketing.guest.track', ['token' => $ticket->guest_token])
            : (Route::has('creators-ticketing.support')
                ? route('creators-ticketing.support', ['ticket' => $ticket->id]) : null);

        // A mail/queue outage must not turn a saved ticket into a failed submission.
        try {
            Notification::route('mail', $email)->notify(new CustomerTicketNotification(
                $ticket->ticket_uid, $url, $reply,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
