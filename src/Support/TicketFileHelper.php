<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class TicketFileHelper
{
    public static function processUploadedFiles(mixed $files, string $ticketId): array
    {
        if (self::hasNoFiles($files)) {
            return [];
        }

        $filesArray = self::normalizeFilesToArray($files);
        self::ensureStorageDirectoryExists($ticketId);
        
        return self::storeAllFiles($filesArray, $ticketId);
    }

    private static function hasNoFiles(mixed $files): bool
    {
        return empty($files);
    }

    private static function normalizeFilesToArray(mixed $files): array
    {
        return is_array($files) ? $files : [$files];
    }

    private static function ensureStorageDirectoryExists(string $ticketId): void
    {
        $directoryPath = "ticket-attachments/{$ticketId}";
        
        if (!Storage::disk('private')->exists($directoryPath)) {
            Storage::disk('private')->makeDirectory($directoryPath);
            Storage::disk('private')->setVisibility($directoryPath, 'private');
        }
    }

    private static function storeAllFiles(array $files, string $ticketId): array
    {
        $storedPaths = [];

        foreach ($files as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                $storedPaths[] = self::storeTemporaryFile($file, $ticketId);
            } elseif (is_string($file)) {
                $storedPaths[] = $file;
            }
        }

        return $storedPaths;
    }

    private static function storeTemporaryFile(TemporaryUploadedFile $file, string $ticketId): string
    {
        $filename = $file->getClientOriginalName();
        $storagePath = "ticket-attachments/{$ticketId}/{$filename}";
        
        Storage::disk('private')->put($storagePath, file_get_contents($file->getRealPath()));
        Storage::disk('private')->setVisibility($storagePath, 'private');
        
        return $storagePath;
    }

}