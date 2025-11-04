<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;
}
