<?php

namespace daacreators\CreatorsTicketing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Models\Department;

class TicketTransferred
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public Department $oldDepartment,
        public Department $newDepartment,
        public mixed $transferredBy
    ) {}
}