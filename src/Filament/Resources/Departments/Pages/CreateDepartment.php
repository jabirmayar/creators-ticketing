<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Departments\Pages;

use Filament\Resources\Pages\CreateRecord;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\DepartmentResource;

class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function afterCreate(): void
    {
        $formId = $this->form->getRawState()['form_id'] ?? null;
        
        if ($formId) {
            $this->record->forms()->sync([$formId]);
        }
    }
}