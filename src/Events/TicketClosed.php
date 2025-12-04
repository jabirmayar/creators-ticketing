<?php

namespace daacreators\CreatorsTicketing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use daacreators\CreatorsTicketing\Models\Ticket;

class TicketClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public mixed $closedBy
    ) {}
}