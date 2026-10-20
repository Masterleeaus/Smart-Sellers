<?php

declare(strict_types=1);

namespace App\Extensions\Chatbot\System\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SecureFileIngestionService
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB
    private const PRIVATE_DISK = 'private';
    private const QUARANTINE_PATH = 'chatbot/quarantine';

    private array $allowedExtensions = [
        'txt' => 'text/plain',
        'pdf' => 'application/pdf',
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'json' => 'application/json',
    ];

    private array $allowedMimeTypes = [
        'text/plain',
        'application/pdf',
        'text/csv',
        'application/json',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
    ];

    /**
     * Validate uploaded file
     */
    public function validateFile(UploadedFile $file): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds maximum limit of ' . (self::MAX_FILE_SIZE / 1024 / 1024) . 'MB';
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!isset($this->allowedExtensions[$extension])) {
            $errors[] = 'File type .' . $extension . ' is not supported';
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if ($mimeType && !in_array($mimeType, $this->allowedMimeTypes, true)) {
            $errors[] = 'Invalid MIME type: ' . $mimeType;
        }

        // Check for dangerous content
        if ($this->containsMacros($file)) {
            $errors[] = 'Files with macros are not allowed';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Store file securely in private storage with server-generated name
     */
    public function storeFileSecurely(UploadedFile $file): array
    {
        // Generate server-side filename
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid()->toString() . '.' . $extension;

        try {
            // Store to private disk (not publicly accessible)
            $path = $file->storeAs(
                self::QUARANTINE_PATH,
                $filename,
                ['disk' => self::PRIVATE_DISK]
            );

            if (!$path) {
                return [
                    'success' => false,
                    'error' => 'Failed to store file',
                ];
            }

            return [
                'success' => true,
                'path' => $path,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'disk' => self::PRIVATE_DISK,
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'error' => 'Error storing file: ' . $exception->getMessage(),
            ];
        }
    }

    /**
     * Move file from quarantine to permanent storage after safe processing
     */
    public function promoteFromQuarantine(string $quarantinePath, string $finalPath): bool
    {
        try {
            $disk = app('filesystem')->disk(self::PRIVATE_DISK);

            if (!$disk->exists($quarantinePath)) {
                return false;
            }

            $content = $disk->get($quarantinePath);
            $disk->put($finalPath, $content);
            $disk->delete($quarantinePath);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check for macro-enabled documents
     */
    private function containsMacros(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());

        // Check for macro-enabled Office formats
        if (in_array($extension, ['xlsm', 'docm', 'pptm', 'xlam', 'potm'], true)) {
            return true;
        }

        // For binary Office files, check for VBA stream
        if ($extension === 'xls') {
            try {
                $path = $file->getRealPath();
                $content = file_get_contents($path, false, null, 0, 8192);
                // Check for VBA signature in OLE compound document
                if (strpos($content, 'Macros') !== false || strpos($content, 'VBA') !== false) {
                    return true;
                }
            } catch (\Throwable) {
                // If we can't read, assume it might have macros
                return true;
            }
        }

        return false;
    }

    /**
     * Get sanitized file metadata
     */
    public function getFileSafeMetadata(UploadedFile $file): array
    {
        return [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'extension' => strtolower($file->getClientOriginalExtension()),
            'mime_type' => $file->getMimeType(),
            'uploaded_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Calculate file hash for integrity checking
     */
    public function calculateFileHash(string $filePath, string $disk = self::PRIVATE_DISK): string
    {
        $diskInstance = app('filesystem')->disk($disk);
        $content = $diskInstance->get($filePath);
        return hash('sha256', $content);
    }
}
