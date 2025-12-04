<?php

namespace daacreators\CreatorsTicketing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $ticketId,
        public string $ticketUid,
        public mixed $deletedBy
    ) {}
}