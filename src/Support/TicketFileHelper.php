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
        $baseDir = Storage::disk('private')->path("ticket-attachments/{$ticketId}");

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        chmod($baseDir, 0755);

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $filename = $file->getClientOriginalName();
                $storagePath = "ticket-attachments/{$ticketId}/{$filename}";
                $fullPath = Storage::disk('private')->path($storagePath);
                Storage::disk('private')->put($storagePath, file_get_contents($file->getRealPath()));
                chmod($fullPath, 0644);
                $storedPaths[] = $storagePath;
            } elseif (is_string($file)) {
                $storedPaths[] = $file;
            }
        }

        return $storedPaths;
    }

}