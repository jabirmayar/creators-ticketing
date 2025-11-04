<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Forms\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use daacreators\CreatorsTicketing\Filament\Resources\Forms\FormResource;

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