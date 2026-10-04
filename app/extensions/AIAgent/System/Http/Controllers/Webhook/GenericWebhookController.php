<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Http\Controllers\Webhook;

use App\Extensions\AIAgent\System\Engine\WorkflowEngine;
use App\Extensions\AIAgent\System\Enums\TriggerTypeEnum;
use App\Extensions\AIAgent\System\Enums\WorkflowStatusEnum;
use App\Extensions\AIAgent\System\Models\AIAgentWorkflow;
use App\Extensions\AIAgent\System\Services\WebhookVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GenericWebhookController extends Controller
{
    public function __construct(
        private readonly WorkflowEngine $engine,
        private readonly WebhookVerificationService $verificationService,
    ) {}

    /**
     * Accept an inbound webhook and fire the matching workflow.
     * The public webhook ID is passed as a route parameter.
     * Requires valid signature verification and timestamp validation.
     */
    public function handle(string $publicId, Request $request): JsonResponse
    {
        // Validate payload size and content type
        $payloadValidation = $this->verificationService->validatePayloadLimits($request);
        if (!$payloadValidation['valid']) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid payload',
                'errors' => $payloadValidation['errors'],
            ], 400);
        }

        // Resolve workflow by PUBLIC ID (not internal ID)
        $workflow = AIAgentWorkflow::query()
            ->where('webhook_public_id', $publicId)
            ->where('trigger_type', TriggerTypeEnum::Webhook)
            ->where('status', WorkflowStatusEnum::Active)
            ->first();

        if ($workflow === null) {
            // Return 404 without revealing if webhook exists
            return response()->json([
                'ok' => false,
                'message' => 'Not found',
            ], 404);
        }

        // Verify webhook signature and configuration
        $verification = $this->verificationService->verifyWebhook($request, $workflow);
        if (!$verification['valid']) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook verification failed',
                'errors' => $verification['errors'],
            ], 401);
        }

        // Generate correlation ID for audit trail
        $correlationId = (string) Str::uuid();

        // Store nonce to prevent replay attacks
        $requestId = $request->header('X-Webhook-Request-Id') ?? $request->input('request_id');
        if ($requestId) {
            $this->verificationService->storeNonce($workflow, $requestId);
        }

        // Fire workflow with normalized payload
        $this->engine->fireWebhookTrigger(
            $workflow,
            $request->all(),
            [
                'correlation_id' => $correlationId,
                'verified' => true,
                'request_id' => $requestId,
            ]
        );

        return response()->json([
            'ok' => true,
            'correlation_id' => $correlationId,
        ], 200);
    }
}
