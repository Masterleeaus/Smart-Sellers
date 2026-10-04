<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add secure ingestion tracking columns to embeddings table
        Schema::table('ext_chatbot_embeddings', function (Blueprint $table) {
            // File storage security
            $table->string('file_hash')->nullable()->after('file'); // SHA256 hash of file
            $table->string('file_storage_disk')->nullable()->after('file_hash'); // Which disk stores the file
            $table->boolean('file_quarantined')->default(false)->after('file_storage_disk');

            // URL ingestion security
            $table->string('source_resolved_ip')->nullable()->after('url'); // IP resolved from DNS
            $table->string('source_final_url')->nullable()->after('source_resolved_ip'); // Final URL after redirects
            $table->integer('source_response_size')->nullable()->after('source_final_url');

            // Ingestion status and async processing
            $table->enum('ingestion_status', ['pending', 'processing', 'completed', 'failed'])->default('completed')->after('trained_at');
            $table->json('ingestion_errors')->nullable()->after('ingestion_status');
            $table->text('processing_notes')->nullable()->after('ingestion_errors');

            // Add indexes for status tracking
            $table->index(['ingestion_status']);
            $table->index(['file_quarantined']);
        });
    }

    public function down(): void
    {
        Schema::table('ext_chatbot_embeddings', function (Blueprint $table) {
            $table->dropIndex(['ingestion_status']);
            $table->dropIndex(['file_quarantined']);
            $table->dropColumn([
                'file_hash',
                'file_storage_disk',
                'file_quarantined',
                'source_resolved_ip',
                'source_final_url',
                'source_response_size',
                'ingestion_status',
                'ingestion_errors',
                'processing_notes',
            ]);
        });
    }
};
