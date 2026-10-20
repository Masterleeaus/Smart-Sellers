<?php

namespace App\Extensions\MarketingBot\System\Services\Whatsapp;

use App\Contracts\LoggerContract;
use App\Extensions\MarketingBot\System\Models\CampaignMessageAnalytic;
use App\Extensions\MarketingBot\System\Models\MarketingConversation;
use App\Extensions\MarketingBot\System\Models\MarketingMessageHistory;
use App\Extensions\MarketingBot\System\Models\Whatsapp\WhatsappChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class WhatsappWebhookService
{
    public function __construct(private readonly LoggerContract $logger)
    {
    }

    public function handle(Request $request, WhatsappChannel $whatsappChannel): void
    {
        try {
            $this->logger->debug('Processing Twilio Whatsapp webhook', [
                'channel_id' => $whatsappChannel->getKey(),
                'message_type' => $request->input('MessageType'),
            ]);

            $marketingConversation = $this->updateOrCreateMarketingConversation($request, $whatsappChannel);

            if ($request->input('MessageType') === 'text' && ! empty($request->input('Body'))) {
                MarketingMessageHistory::query()->create([
                    'conversation_id' => $marketingConversation->getKey(),
                    'message_id'      => $request->input('SmsSid'),
                    'model'           => null,
                    'role'            => 'user',
                    'message'         => $request->input('Body'),
                    'type'            => 'default',
                    'message_type'    => 'text',
                    'content_type'    => 'text',
                    'created_at'      => now(),
                ]);

                $this->logger->info('Whatsapp message received and stored', [
                    'conversation_id' => $marketingConversation->getKey(),
                    'message_id' => $request->input('SmsSid'),
                    'channel_id' => $whatsappChannel->getKey(),
                ]);

                app(WhatsappReplyService::class)
                    ->setWhatsappChannel($whatsappChannel)
                    ->sendReply($request, $marketingConversation);
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to process Twilio Whatsapp webhook', [
                'channel_id' => $whatsappChannel->getKey(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle incoming Meta Cloud API webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleMeta(array $payload, WhatsappChannel $whatsappChannel): void
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? [];

        $message = $value['messages'][0] ?? null;

        if ($message) {
            $from = $message['from'] ?? null;

            if ($from) {
                $conversation = MarketingConversation::query()
                    ->firstOrCreate([
                        'user_id'             => $whatsappChannel->getAttribute('user_id'),
                        'type'                => 'whatsapp',
                        'session_id'          => $from,
                        'whatsapp_channel_id' => $whatsappChannel->getKey(),
                    ], [
                        'conversation_name' => $from,
                        'customer_payload'  => [
                            'AccountSid' => $from,
                            'From'       => $from,
                        ],
                    ]);

                $messageType = $message['type'] ?? 'text';
                $body = $message['text']['body'] ?? null;
                $messageId = $message['id'] ?? null;

                if ($messageType === 'text' && ! empty($body)) {
                    MarketingMessageHistory::query()->create([
                        'conversation_id' => $conversation->getKey(),
                        'message_id'      => $messageId,
                        'model'           => null,
                        'role'            => 'user',
                        'message'         => $body,
                        'type'            => 'default',
                        'message_type'    => 'text',
                        'content_type'    => 'text',
                        'created_at'      => now(),
                    ]);

                    app(WhatsappReplyService::class)
                        ->setWhatsappChannel($whatsappChannel)
                        ->sendMetaReply($body, $from, $conversation);
                }
            }
        }

        $statuses = $value['statuses'] ?? [];

        foreach ($statuses as $statusEntry) {
            $wamid = $statusEntry['id'] ?? null;
            $status = $statusEntry['status'] ?? null;
            $timestamp = $statusEntry['timestamp'] ?? null;

            if (! $wamid || ! $status) {
                continue;
            }

            $record = CampaignMessageAnalytic::query()
                ->where('meta_message_id', $wamid)
                ->first();

            if (! $record) {
                continue;
            }

            $ts = $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : now();
            $update = ['status' => $status];

            if ($status === 'delivered') {
                $update['delivered_at'] = $ts;
            } elseif ($status === 'read') {
                $update['read_at'] = $ts;
            } elseif ($status === 'failed') {
                $update['failed_at'] = $ts;
                $update['error_code'] = $statusEntry['errors'][0]['code'] ?? null;
                $update['error_title'] = $statusEntry['errors'][0]['title'] ?? null;
            }

            $record->update($update);
        }
    }

    public function updateOrCreateMarketingConversation(Request $request, WhatsappChannel $whatsappChannel): Model|Builder
    {
        return MarketingConversation::query()
            ->firstOrCreate([
                'user_id'             => $whatsappChannel->getAttribute('user_id'),
                'type'                => 'whatsapp',
                'session_id'          => $request->input('WaId'),
                'whatsapp_channel_id' => $whatsappChannel->getKey(),
            ], [
                'conversation_name' => $request->input('WaId', 'Number'),
                'customer_payload'  => [
                    'AccountSid' => $request->input('AccountSid'),
                    'From'       => $request->input('From'),
                ],
            ]);
    }
}
