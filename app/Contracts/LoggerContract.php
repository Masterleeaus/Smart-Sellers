<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Contract for structured logging across all extensions.
 *
 * Provides consistent logging interface for:
 * - Successful operations (extension registration, deployments, etc.)
 * - Failed operations (database errors, validation failures, auth denials)
 * - Performance metrics (query times, batch progress, cache rates)
 * - Security events (webhook verification, unauthorized access, rate limits)
 *
 * All context data is structured to be JSON-friendly and excludes sensitive data.
 */
interface LoggerContract
{
    /**
     * Log an informational message (successful operations, state changes).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional structured context (tenant_id, operation_id, duration_ms, etc.)
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a warning message (deprecated features, performance degradation, rate limiting).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional structured context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log an error message (failures, exceptions, authorization denied).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional structured context (error_code, user_id, operation_id, etc.)
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a debug message (low-level operation details).
     *
     * @param string $message The log message
     * @param array<string, mixed> $context Additional structured context
     */
    public function debug(string $message, array $context = []): void;
}
