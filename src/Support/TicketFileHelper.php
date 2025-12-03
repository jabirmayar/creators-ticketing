<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TicketFileHelper
{
    public static function processUploadedFiles(mixed $files, string $ticketId): array
    {
        if (empty($files)) return [];

        $files = is_array($files) ? $files : [$files];
        $storedPaths = [];

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $filename = $file->getClientOriginalName();
                $storagePath = "ticket-attachments/{$ticketId}/{$filename}";
                Storage::disk('private')->put($storagePath, file_get_contents($file->getRealPath()));
                $storedPaths[] = $storagePath;
            } elseif (is_string($file)) {
                $storedPaths[] = $file;
            }
        }

        return $storedPaths;
    }

}