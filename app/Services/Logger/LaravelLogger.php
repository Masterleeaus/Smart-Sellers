<?php

declare(strict_types=1);

namespace App\Services\Logger;

use App\Contracts\LoggerContract;
use Illuminate\Log\LogManager;

/**
 * Laravel implementation of LoggerContract.
 *
 * Wraps Laravel's logging system to provide structured logging
 * across all extensions while maintaining consistency.
 */
class LaravelLogger implements LoggerContract
{
    public function __construct(private readonly LogManager $logManager)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->logManager->info($message, $this->sanitizeContext($context));
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logManager->warning($message, $this->sanitizeContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        $this->logManager->error($message, $this->sanitizeContext($context));
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logManager->debug($message, $this->sanitizeContext($context));
    }

    /**
     * Sanitize context data to prevent logging sensitive information.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credential', 'auth_code'];

        $sanitized = [];
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey($key, $sensitiveKeys)) {
                $sanitized[$key] = '***REDACTED***';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if a key is considered sensitive.
     *
     * @param string $key
     * @param array<string> $sensitiveKeys
     */
    private function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $lowerKey = strtolower($key);

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains($lowerKey, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }
}
