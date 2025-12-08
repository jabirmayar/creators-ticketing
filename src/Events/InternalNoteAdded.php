<?php

namespace daacreators\CreatorsTicketing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\TicketReply;

class InternalNoteAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketReply $note
    ) {}
}