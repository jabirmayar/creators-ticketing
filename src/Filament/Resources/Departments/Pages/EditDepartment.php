<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Departments\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\DepartmentResource;

class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['form_id'] = $this->record->forms()->first()?->id;
        return $data;
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $formId = $this->data['form_id'] ?? null;
        
        unset($this->data['form_id']);
        
        parent::save($shouldRedirect, $shouldSendSavedNotification);
        
        if ($formId) {
            $this->record->forms()->sync([$formId]);
        } else {
            $this->record->forms()->detach();
        }

        $this->record->refresh();
        $this->record->load('forms');
        
        $this->data['form_id'] = $this->record->forms()->first()?->id;
    }
}