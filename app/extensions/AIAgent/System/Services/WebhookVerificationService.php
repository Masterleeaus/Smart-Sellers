<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Services;

use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use Illuminate\Http\Request;

class WebhookVerificationService
{
    /**
     * Verify webhook signature and configuration
     */
    public function verifyWebhook(Request $request, AIAgentWorkflow $workflow): array
    {
        $errors = [];

        // Verify webhook is enabled by checking secret configuration
        if (empty($workflow->webhook_secret_ref)) {
            $errors[] = 'Webhook secret not configured - webhook is disabled';
        }

        // Verify timestamp is not too old (within 5 minutes)
        $timestamp = $request->header('X-Webhook-Timestamp');
        if ($timestamp) {
            $requestTime = (int) $timestamp;
            $currentTime = (int) microtime(true) * 1000; // milliseconds
            if (abs($currentTime - $requestTime) > 300000) { // 5 minutes
                $errors[] = 'Request timestamp is expired';
            }
        }

        // Verify signature if secret is configured
        if (!empty($workflow->webhook_secret_ref)) {
            $signature = $request->header('X-Webhook-Signature');
            if (empty($signature)) {
                $errors[] = 'Missing webhook signature';
            } else {
                // Get secret from configuration/vault
                $secret = $this->getWebhookSecret($workflow->webhook_secret_ref);
                if (!$secret) {
                    $errors[] = 'Unable to retrieve webhook secret';
                } else {
                    // Verify HMAC-SHA256 signature
                    $expectedSignature = hash_hmac(
                        'sha256',
                        $request->getContent(),
                        $secret,
                        false
                    );

                    if (!hash_equals($expectedSignature, $signature)) {
                        $errors[] = 'Invalid webhook signature';
                    }
                }
            }
        }

        // Check for replay attacks using nonce/event ID
        $requestId = $request->header('X-Webhook-Request-Id') ?? $request->input('request_id');
        if ($requestId) {
            if ($this->isNonceReplayed($workflow, $requestId)) {
                $errors[] = 'Request has already been processed (replay attack)';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Mark a nonce as used
     */
    public function storeNonce(AIAgentWorkflow $workflow, string $nonce): void
    {
        $nonces = $workflow->webhook_nonces ?? [];

        // Keep only recent nonces (within 24 hours)
        $cutoff = now()->subHours(24)->timestamp;
        $nonces = array_filter(
            $nonces,
            fn ($item) => ($item['timestamp'] ?? 0) > $cutoff
        );

        // Add new nonce
        $nonces[$nonce] = [
            'timestamp' => now()->timestamp,
            'used_at' => now()->toIso8601String(),
        ];

        $workflow->update(['webhook_nonces' => $nonces]);
    }

    /**
     * Check if a nonce has already been used
     */
    private function isNonceReplayed(AIAgentWorkflow $workflow, string $nonce): bool
    {
        $nonces = $workflow->webhook_nonces ?? [];
        return isset($nonces[$nonce]);
    }

    /**
     * Get webhook secret from configuration/vault
     * This is a placeholder - in production, integrate with your Credential Vault
     */
    private function getWebhookSecret(string $secretRef): ?string
    {
        // TODO: Implement Credential Vault integration
        // For now, just return from config
        return config("ai-agent.webhook_secrets.{$secretRef}");
    }

    /**
     * Validate webhook payload limits
     */
    public function validatePayloadLimits(Request $request): array
    {
        $errors = [];
        $maxSize = 1024 * 1024; // 1MB

        // Check content length
        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
        if ($contentLength > $maxSize) {
            $errors[] = 'Payload exceeds maximum size limit';
        }

        // Check content type
        $contentType = $request->header('Content-Type') ?? '';
        if (!in_array($contentType, ['application/json', 'application/x-www-form-urlencoded'])) {
            $errors[] = 'Unsupported content type';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
};
