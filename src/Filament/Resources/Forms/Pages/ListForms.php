<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Forms\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\Forms\FormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListForms extends ListRecords
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
