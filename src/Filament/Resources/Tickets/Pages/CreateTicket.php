<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;

use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use daacreators\CreatorsTicketing\Models\Form;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        
        $formId = $record->form_id;
        if (!$formId && $record->department) {
            $formId = $record->department->forms()->where('is_active', true)->first()?->id;
        }

        if (!$formId) return;

        $form = Form::with('fields')->find($formId);
        $customFields = $record->custom_fields ?? [];
        $hasChanges = false;
        $disk = Storage::disk('private');

        foreach ($form->fields as $field) {
            if (in_array($field->type, ['file', 'file_multiple'])) {
                
                $uploadedFiles = $customFields[$field->name] ?? null;

                if (empty($uploadedFiles)) continue;

                $files = is_array($uploadedFiles) ? $uploadedFiles : [$uploadedFiles];
                $newPaths = [];
                $filesMoved = false;

                foreach ($files as $filePath) {
                    if (str_contains($filePath, 'ticket-attachments/temp/')) {
                        
                        $filename = basename($filePath);
                        $newPath = "ticket-attachments/{$record->id}/{$filename}";

                        if ($disk->exists($filePath)) {
                            $disk->move($filePath, $newPath);
                            $newPaths[] = $newPath;
                            $filesMoved = true;
                        } else {
                            $newPaths[] = $filePath; 
                        }
                    } else {
                        $newPaths[] = $filePath;
                    }
                }

                if ($filesMoved) {
                    if ($field->type === 'file_multiple') {
                        $customFields[$field->name] = $newPaths;
                    } else {
                        $customFields[$field->name] = head($newPaths);
                    }
                    $hasChanges = true;
                }
            }
        }

        if ($hasChanges) {
            $record->update(['custom_fields' => $customFields]);
        }
    }
}