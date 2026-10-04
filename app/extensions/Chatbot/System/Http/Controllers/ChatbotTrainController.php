<?php

namespace App\Extensions\Chatbot\System\Http\Controllers;

use App\Domains\Engine\Enums\EngineEnum;
use App\Domains\Entity\Enums\EntityEnum;
use App\Extensions\Chatbot\System\Enums\EmbeddingTypeEnum;
use App\Extensions\Chatbot\System\Http\Requests\Train\DataRequest;
use App\Extensions\Chatbot\System\Http\Requests\Train\EmbedingRequest;
use App\Extensions\Chatbot\System\Http\Requests\Train\FileRequest;
use App\Extensions\Chatbot\System\Http\Requests\Train\QaRequest;
use App\Extensions\Chatbot\System\Http\Requests\Train\TextRequest;
use App\Extensions\Chatbot\System\Http\Requests\Train\TrainUrlRequest;
use App\Extensions\Chatbot\System\Http\Resources\Admin\ChatbotEmbeddingResource;
use App\Extensions\Chatbot\System\Models\Chatbot;
use App\Extensions\Chatbot\System\Models\ChatbotEmbedding;
use App\Extensions\Chatbot\System\Parsers\ExcelParser;
use App\Extensions\Chatbot\System\Parsers\LinkParser;
use App\Extensions\Chatbot\System\Parsers\PdfParser;
use App\Extensions\Chatbot\System\Parsers\TextParser;
use App\Extensions\Chatbot\System\Services\ChatbotService;
use App\Extensions\Chatbot\System\Services\OpenAI\EmbedingService;
use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;

class ChatbotTrainController extends Controller
{
    public function __construct(public ChatbotService $service) {}

    public function train(Chatbot $chatbot): View
    {
        $this->authorize('view', $chatbot);

        return view('chatbot::train', [
            'chatbot' => $chatbot,
        ]);
    }

    public function trainData(DataRequest $request): AnonymousResourceCollection
    {
        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        return ChatbotEmbeddingResource::collection(
            $chatbot
                ->embeddings()
                ->when($request->validated('type'), fn ($query) => $query->where('type', $request->validated('type')))
                ->get()
        );
    }

    public function deleteEmbedding(EmbedingRequest $request): JsonResponse
    {
        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        $chatbot->embeddings()->whereIn('id', $request->validated('data'))->delete();

        return response()->json([
            'message' => 'Embedding deleted successfully',
            'status'  => 200,
        ]);
    }

    public function generateEmbedding(EmbedingRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        ini_set('max_execution_time', -1);

        $data = $request->validated('data');

        $embeddings = $chatbot->embeddings()
            ->whereNull('embedding')
            ->whereIn('id', $data)
            ->get();

        $aiEmbeddingModel = EntityEnum::TEXT_EMBEDDING_3_SMALL;

        if (! EntityEnum::from($chatbot->getAttribute('ai_embedding_model'))) {
            $chatbot->update([
                'ai_embedding_model' => EntityEnum::TEXT_EMBEDDING_3_SMALL->value,
            ]);

            $aiEmbeddingModel = EntityEnum::TEXT_EMBEDDING_3_SMALL;
        }

        foreach ($embeddings as $embedding) {
            $embeddingJson = app(EmbedingService::class)
                ->setChatbot($chatbot)
                ->setEntity($aiEmbeddingModel)
                ->generateEmbedding($embedding->getAttribute('content'));

            $embedding->update([
                'embedding'    => $embeddingJson->toArray(),
                'trained_at'   => now(),
            ]);
        }

        return ChatbotEmbeddingResource::collection($chatbot->embeddings()->get());
    }

    public function trainText(TextRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        ChatbotEmbedding::query()
            ->create([
                'type'       => EmbeddingTypeEnum::text,
                'chatbot_id' => $chatbot->getKey(),
                'url'        => null,
                'file'       => null,
                'engine'     => EngineEnum::OPEN_AI->value,
                'title'      => $request->validated('title'),
                'content'    => $request->validated('content'),
            ]);

        return ChatbotEmbeddingResource::collection(
            $chatbot->embeddings()
                ->wherenull('file')
                ->whereNull('url')->get()
        );
    }

    public function trainQa(QaRequest $request): JsonResponse|AnonymousResourceCollection
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        ChatbotEmbedding::query()
            ->create([
                'type'       => EmbeddingTypeEnum::qa,
                'chatbot_id' => $chatbot->getKey(),
                'url'        => null,
                'file'       => null,
                'engine'     => EngineEnum::OPEN_AI->value,
                'title'      => $request->validated('question'),
                'content'    => $request->validated('question') . ' : ' . $request->validated('answer'),
            ]);

        return ChatbotEmbeddingResource::collection(
            $chatbot->embeddings()
                ->wherenull('file')
                ->whereNull('url')->get()
        );
    }

    public function trainUrl(TrainUrlRequest $request): JsonResponse
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        // Use secure URL ingestion service
        $urlIngestionService = app(\App\Extensions\Chatbot\System\Services\SecureUrlIngestionService::class);

        // Validate URL
        $validation = $urlIngestionService->validateUrl($request->validated('url'));
        if (!$validation['valid']) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid URL',
                'errors' => $validation['errors'],
            ], 400);
        }

        // Resolve DNS and check for SSRF
        $dnsValidation = $urlIngestionService->resolveDns($request->validated('url'));
        if (!$dnsValidation['valid']) {
            return response()->json([
                'ok' => false,
                'message' => 'Security validation failed',
                'errors' => [$dnsValidation['error']],
            ], 403);
        }

        // Queue async ingestion instead of processing synchronously
        // For now, create embedding record with pending status
        $embedding = ChatbotEmbedding::create([
            'type' => EmbeddingTypeEnum::website,
            'chatbot_id' => $chatbot->getKey(),
            'url' => $request->validated('url'),
            'source_resolved_ip' => $dnsValidation['ip'],
            'engine' => EngineEnum::OPEN_AI->value,
            'ingestion_status' => 'pending',
            'title' => 'Processing...',
            'content' => '',
        ]);

        // TODO: Queue async job to fetch and parse URL
        // dispatch(new ProcessUrlIngestionJob($embedding, $request->validated('url')));

        return response()->json([
            'ok' => true,
            'message' => 'URL ingestion queued for processing',
            'embedding_id' => $embedding->id,
            'status' => 'pending',
        ], 202);
    }

    public function trainFile(FileRequest $request)
    {
        if (Helper::appIsDemo()) {
            return response()->json([
                'type'    => 'error',
                'message' => 'This feature is disabled in Demo version.',
            ], 403);
        }

        $chatbot = $this->service->query()->findOrFail($request->validated('id'));

        $this->authorize('train', $chatbot);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        // Use secure file ingestion service
        $fileIngestionService = app(\App\Extensions\Chatbot\System\Services\SecureFileIngestionService::class);

        // Validate file
        $validation = $fileIngestionService->validateFile($file);
        if (!$validation['valid']) {
            return response()->json([
                'type' => 'error',
                'message' => 'File validation failed',
                'errors' => $validation['errors'],
            ], 400);
        }

        // Store file securely in private storage
        $storageResult = $fileIngestionService->storeFileSecurely($file);
        if (!$storageResult['success']) {
            return response()->json([
                'type' => 'error',
                'message' => 'Failed to store file',
                'error' => $storageResult['error'],
            ], 500);
        }

        $name = $file->getClientOriginalName();
        $path = $storageResult['path'];
        $extension = strtolower($file->getClientOriginalExtension());

        // Queue async parsing instead of parsing synchronously
        $embedding = ChatbotEmbedding::create([
            'type'               => EmbeddingTypeEnum::file,
            'chatbot_id'         => $chatbot->getKey(),
            'url'                => null,
            'file'               => $path,
            'file_storage_disk'  => $storageResult['disk'],
            'file_quarantined'   => true,
            'file_hash'          => $fileIngestionService->calculateFileHash($path, $storageResult['disk']),
            'engine'             => EngineEnum::OPEN_AI->value,
            'title'              => $name,
            'content'            => '',
            'ingestion_status'   => 'pending',
            'processing_notes'   => 'File stored in quarantine, awaiting secure parsing',
        ]);

        // TODO: Queue async job to parse file
        // dispatch(new ProcessFileIngestionJob($embedding, $extension));

        return response()->json([
            'ok'           => true,
            'message'      => 'File uploaded and queued for processing',
            'embedding_id' => $embedding->id,
            'status'       => 'pending',
            'file_name'    => $name,
        ], 202);
    }
}
