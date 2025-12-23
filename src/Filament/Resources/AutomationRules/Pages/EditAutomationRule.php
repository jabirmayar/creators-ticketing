<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\AutomationRuleResource;

class EditAutomationRule extends EditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}