<?php

namespace App\Extensions\ChatbotReview\System\Services;

use App\Contracts\LoggerContract;
use App\Extensions\Chatbot\System\Models\ChatbotConversation;
use Throwable;

class ChatbotReviewService
{
    public function __construct(private readonly LoggerContract $logger)
    {
    }

    public function requestReview(ChatbotConversation $conversation, string $reason): ChatbotConversation
    {
        try {
            $conversation->loadMissing('chatbot');

            if (! $conversation->chatbot?->getAttribute('is_review_enabled')) {
                $this->logger->info('Review disabled for chatbot', [
                    'conversation_id' => $conversation->getAttribute('id'),
                    'chatbot_id' => $conversation->chatbot?->getAttribute('id'),
                ]);
                return $conversation;
            }

            if ($conversation->getAttribute('review_requested_at')) {
                $this->logger->debug('Review already requested for conversation', [
                    'conversation_id' => $conversation->getAttribute('id'),
                ]);
                return $conversation;
            }

            $conversation->forceFill([
                'review_requested_at'   => now(),
                'review_request_reason' => $reason,
            ])->save();

            $this->logger->info('Review requested for conversation', [
                'conversation_id' => $conversation->getAttribute('id'),
                'reason' => $reason,
                'chatbot_id' => $conversation->chatbot?->getAttribute('id'),
            ]);

            return $conversation;
        } catch (Throwable $e) {
            $this->logger->error('Failed to request review', [
                'conversation_id' => $conversation->getAttribute('id'),
                'reason' => $reason,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    public function submitReview(ChatbotConversation $conversation, string $message, ?string $selectedResponse = null): ChatbotConversation
    {
        try {
            $conversation->forceFill([
                'review_message'           => $message,
                'review_selected_response' => $selectedResponse,
                'review_submitted_at'      => now(),
            ])->save();

            $this->logger->info('Review submitted for conversation', [
                'conversation_id' => $conversation->getAttribute('id'),
                'has_selected_response' => $selectedResponse !== null,
                'message_length' => strlen($message),
            ]);

            return $conversation;
        } catch (Throwable $e) {
            $this->logger->error('Failed to submit review', [
                'conversation_id' => $conversation->getAttribute('id'),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }
}
