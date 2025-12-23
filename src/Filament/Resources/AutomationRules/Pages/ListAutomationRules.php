<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\AutomationRuleResource;

class ListAutomationRules extends ListRecords
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}