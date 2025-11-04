<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketStatus extends CreateRecord
{
    protected static string $resource = TicketStatusResource::class;
}
