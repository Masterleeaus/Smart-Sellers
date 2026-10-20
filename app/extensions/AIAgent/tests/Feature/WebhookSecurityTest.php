<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\Tests\Feature;

use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookSecurityTest extends TestCase
{
    protected AIAgentWorkflow $workflow;
    protected string $webhookSecret = 'test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        // Create a workflow with webhook security configured
        $this->workflow = AIAgentWorkflow::create([
            'user_id' => 1,
            'webhook_public_id' => Str::uuid()->toString(),
            'webhook_secret_ref' => 'test-secret',
            'name' => 'Test Webhook Workflow',
            'trigger_type' => TriggerTypeEnum::Webhook,
            'status' => WorkflowStatusEnum::Active,
        ]);
    }

    public function test_webhook_request_without_signature_is_rejected(): void
    {
        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data']
        );

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
    }

    public function test_webhook_request_with_invalid_signature_is_rejected(): void
    {
        $payload = json_encode(['test' => 'data']);
        $invalidSignature = 'invalid-signature';

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data'],
            [
                'X-Webhook-Signature' => $invalidSignature,
                'X-Webhook-Request-Id' => Str::uuid()->toString(),
                'X-Webhook-Timestamp' => (string) (microtime(true) * 1000),
            ]
        );

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
    }

    public function test_webhook_request_with_expired_timestamp_is_rejected(): void
    {
        // Set timestamp to 10 minutes in the past
        $expiredTimestamp = (microtime(true) * 1000) - (10 * 60 * 1000);
        $payload = json_encode(['test' => 'data']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret, false);

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data'],
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Request-Id' => Str::uuid()->toString(),
                'X-Webhook-Timestamp' => (string) $expiredTimestamp,
            ]
        );

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
    }

    public function test_webhook_request_to_nonexistent_workflow_returns_404(): void
    {
        $fakePublicId = Str::uuid()->toString();
        $payload = json_encode(['test' => 'data']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret, false);

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $fakePublicId]),
            ['test' => 'data'],
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Request-Id' => Str::uuid()->toString(),
                'X-Webhook-Timestamp' => (string) (microtime(true) * 1000),
            ]
        );

        $response->assertStatus(404);
    }

    public function test_webhook_request_with_oversized_payload_is_rejected(): void
    {
        $largeData = array_fill(0, 100000, 'x');

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            $largeData,
            [
                'X-Webhook-Signature' => 'test-signature',
                'Content-Length' => (string) (2 * 1024 * 1024), // 2MB
            ]
        );

        $response->assertStatus(400);
        $response->assertJsonPath('ok', false);
    }

    public function test_webhook_without_secret_configuration_is_disabled(): void
    {
        $this->workflow->update(['webhook_secret_ref' => null]);

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data']
        );

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
    }

    public function test_webhook_request_resolves_by_public_id_not_internal_id(): void
    {
        $newWorkflow = AIAgentWorkflow::create([
            'user_id' => 2,
            'webhook_public_id' => Str::uuid()->toString(),
            'webhook_secret_ref' => 'test-secret',
            'name' => 'Another Workflow',
            'trigger_type' => TriggerTypeEnum::Webhook,
            'status' => WorkflowStatusEnum::Active,
        ]);

        // Try to access with old workflow's ID - should get 404
        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => (string) $this->workflow->id]),
            ['test' => 'data'],
            ['X-Webhook-Signature' => 'test']
        );

        $response->assertStatus(404);
    }

    public function test_replay_attack_is_prevented(): void
    {
        $requestId = Str::uuid()->toString();
        $payload = json_encode(['test' => 'data']);
        $signature = hash_hmac('sha256', $payload, $this->webhookSecret, false);

        // Store the nonce
        $this->workflow->update([
            'webhook_nonces' => [
                $requestId => [
                    'timestamp' => now()->timestamp,
                    'used_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        // Try to replay the same request
        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data'],
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Request-Id' => $requestId,
                'X-Webhook-Timestamp' => (string) (microtime(true) * 1000),
            ]
        );

        $response->assertStatus(401);
        $response->assertJsonPath('ok', false);
    }

    public function test_inactive_workflow_rejects_webhook(): void
    {
        $this->workflow->update(['status' => WorkflowStatusEnum::Inactive]);

        $response = $this->postJson(
            route('api.ai-agent.webhook', ['publicId' => $this->workflow->webhook_public_id]),
            ['test' => 'data'],
            ['X-Webhook-Signature' => 'test']
        );

        $response->assertStatus(404);
    }
}
