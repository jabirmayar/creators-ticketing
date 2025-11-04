<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketStatus extends EditRecord
{
    protected static string $resource = TicketStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
