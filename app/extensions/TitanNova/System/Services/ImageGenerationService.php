<?php

namespace App\Extensions\TitanNova\System\Services;

use App\Domains\Entity\Enums\EntityEnum;
use App\Helpers\Classes\ApiHelper;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageGenerationService
{
    protected string $model = 'fal-ai/flux-pro';

    protected string $falApiKey;

    public function __construct() {}

    /**
     * Generate an image using Flux Pro (async with webhook)
     * Returns request_id for tracking
     */
    public function generateAttachmentForTask(string $taskInstructions, array $options = []): array
    {
        return $this->generateImageForTask($taskInstructions, $options);
    }

    /** @deprecated Use generateAttachmentForTask(). */
    public function generateImageForTask(string $taskContent, array $options = []): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            // Create image prompt from task content
            $imagePrompt = $this->createImagePrompt($taskContent, $options);

            // Submit to Fal.ai (async)
            $result = $this->submitToFalAi($imagePrompt, $options);

            if (! $result['success']) {
                return [
                    'success' => false,
                    'error'   => $result['error'] ?? 'Failed to submit image creation',
                ];
            }

            return [
                'success'      => true,
                'request_id'   => $result['request_id'],
                'prompt'       => $imagePrompt,
                'status'       => $result['status'] ?? 'pending', // Image is being generated
                'image_url'    => $result['image_url'] ?? null,
                'submitted_at' => now()->toIso8601String(),
            ];
        } catch (Exception $e) {
            Log::error('ImageGenerationService Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Submit image creation request to Fal.ai
     */
    protected function submitToFalAi(string $prompt, array $options = []): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            $webhookUrl = route('dashboard.user.titan-nova.agent.fal-webhook');

            $response = Http::withHeaders([
                'Authorization' => 'Key ' . $this->falApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://fal.run/fal-ai/flux-pro', [
                'prompt'                => $prompt,
                'image_size'            => $options['image_size'] ?? 'landscape_4_3',
                'num_inference_steps'   => 28,
                'guidance_scale'        => 3.5,
                'num_images'            => 1,
                'enable_safety_checker' => true,
                'output_format'         => 'jpeg',
                'webhook_url'           => $webhookUrl,
            ]);

            if ($response->failed()) {
                Log::error('Fal.ai API Error: ' . $response->body());

                return [
                    'success' => false,
                    'error'   => 'Fal.ai API request failed',
                ];
            }

            Log::info('Fal.ai API Response: ' . $response->body());

            $data = $response->json();

            $imageUrl = $data['images'][0]['url'] ?? null;
            $status = strtolower($data['status'] ?? ($imageUrl ? 'completed' : 'pending'));

            $result = [
                'success'    => true,
                'request_id' => $data['request_id'] ?? uniqid('fal_', true),
                'status'     => $status,
            ];

            if ($imageUrl) {
                $result['image_url'] = $this->downloadAndStoreImage($imageUrl);
            }

            Log::info('result: ', $result);

            return $result;
        } catch (Exception $e) {
            Log::error('Fal.ai submission error: ' . $e->getMessage());

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll Fal.ai for image creation status (alternative to webhook)
     */
    public function checkStatus(string $requestId): array
    {
        $this->falApiKey = ApiHelper::setFalAIKey();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Key ' . $this->falApiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(10)->get("https://fal.run/fal-ai/flux-pro/requests/{$requestId}/status");

            if ($response->failed()) {
                return [
                    'success' => false,
                    'status'  => 'failed',
                ];
            }

            $data = $response->json();

            if ($data['status'] === 'COMPLETED') {
                $imageUrl = $data['images'][0]['url'] ?? null;

                if ($imageUrl) {
                    $storedPath = $this->downloadAndStoreImage($imageUrl);

                    return [
                        'success'   => true,
                        'status'    => 'completed',
                        'image_url' => $storedPath,
                    ];
                }
            }

            return [
                'success' => true,
                'status'  => strtolower($data['status']), // pending, processing, completed, failed
            ];
        } catch (Exception $e) {
            Log::error('Fal.ai status check error: ' . $e->getMessage());

            return [
                'success' => false,
                'status'  => 'error',
            ];
        }
    }

    /**
     * Create an image prompt from task content
     */
    protected function createImagePrompt(string $taskContent, array $options = []): string
    {
        try {
            ApiHelper::setOpenAiKey();

            $systemPrompt = <<<'SYSTEM'
You are an expert at creating image prompts for AI image creation.
Create a detailed, visual prompt for a blog task featured image.

Guidelines:
- Focus on visual elements, composition, colors, and style
- Make it relevant to the task content
- Keep it concise but descriptive (max 100 words)
- Avoid text, logos, or brand names in the image
- Create professional, eye-catching imagery
- Use modern, clean aesthetics
SYSTEM;

            $userPrompt = <<<PROMPT
Create an image creation prompt for this blog task title:

"{$taskContent}"

Return only the image prompt, nothing else.
PROMPT;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => EntityEnum::GPT_5_MINI->value,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role'    => 'user',
                        'content' => $userPrompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 200,
            ]);

            if ($response->failed()) {
                Log::error('Failed to create image prompt: ' . $response->body());

                return 'Professional image, modern design, clean composition, vibrant colors';
            }

            return trim($response->json('choices.0.message.content'));
        } catch (Exception $e) {
            Log::warning('Error creating image prompt: ' . $e->getMessage());

            return 'Professional image, modern design, clean composition, vibrant colors';
        }
    }

    /**
     * Generate image using Flux Pro API
     */
    protected function generateWithFluxPro(string $prompt): ?string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . ApiHelper::setOpenAiKey(),
                'Content-Type'  => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/images/creations', [
                'model'   => 'dall-e-3',
                'prompt'  => $prompt,
                'n'       => 1,
                'size'    => '1024x1024',
                'quality' => 'standard',
            ]);

            if ($response->failed()) {
                Log::error('Flux Pro API Error: ' . $response->body());

                return null;
            }

            $imageUrl = $response->json('data.0.url');

            return $imageUrl;
        } catch (Exception $e) {
            Log::error('Flux Pro creation error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Download and store the generated image
     */
    protected function downloadAndStoreImage(string $url): string
    {
        try {
            $imageContents = file_get_contents($url);
            $filename = 'titan-nova/' . uniqid('task_', true) . '.png';

            Storage::disk('public')->put($filename, $imageContents);

            return '/uploads/' . $filename;
        } catch (Exception $e) {
            Log::error('Failed to download/store image: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Set the AI model to use
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
