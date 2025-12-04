<?php

namespace daacreators\CreatorsTicketing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Enums\TicketPriority;

class TicketPriorityChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public TicketPriority $oldPriority,
        public TicketPriority $newPriority,
        public mixed $changedBy
    ) {}
}