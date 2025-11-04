<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketStatuses extends ListRecords
{
    protected static string $resource = TicketStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
