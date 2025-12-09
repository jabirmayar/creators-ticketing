<?php

namespace daacreators\CreatorsTicketing\Support;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Log;

class TicketFileHelper
{
    public static function processUploadedFiles(mixed $files, string $ticketId): array
    {
        if (self::isEmptyFileInput($files)) {
            return [];
        }

        $filesToProcess = self::convertToArray($files);
        self::createAttachmentDirectory($ticketId);
        
        return self::processFileCollection($filesToProcess, $ticketId);
    }

    private static function isEmptyFileInput(mixed $files): bool
    {
        if (is_array($files)) {
            return count(array_filter($files)) === 0;
        }
        
        return empty($files);
    }

    private static function convertToArray(mixed $files): array
    {
        if (is_array($files)) {
            return array_filter($files);
        }
        
        return $files ? [$files] : [];
    }

    private static function createAttachmentDirectory(string $ticketId): void
    {
        $directoryPath = Storage::disk('private')->path("ticket-attachments/{$ticketId}");
        
        if (!file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }
    }

    private static function processFileCollection(array $files, string $ticketId): array
    {
        return array_map(function ($file) use ($ticketId) {
            return self::processSingleFile($file, $ticketId);
        }, $files);
    }

    private static function processSingleFile(mixed $file, string $ticketId): string
    {
        if ($file instanceof TemporaryUploadedFile) {
            return self::storeUploadedFile($file, $ticketId);
        }
        
        if (is_string($file) && Storage::disk('private')->exists($file)) {
            return $file;
        }
        
        throw new \InvalidArgumentException('Invalid file type provided');
    }

    private static function storeUploadedFile(TemporaryUploadedFile $file, string $ticketId): string
    {
        $originalName = $file->getClientOriginalName();
        $safeFileName = self::generateSafeFileName($originalName);
        $storagePath = "ticket-attachments/{$ticketId}/{$safeFileName}";
        
        $fileContents = file_get_contents($file->getRealPath());
        Storage::disk('private')->put($storagePath, $fileContents);
        
        return $storagePath;
    }

    private static function generateSafeFileName(string $originalName): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $nameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);
        
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nameWithoutExtension);
        $safeName = substr($safeName, 0, 100);
        
        return $extension ? "{$safeName}.{$extension}" : $safeName;
    }

}