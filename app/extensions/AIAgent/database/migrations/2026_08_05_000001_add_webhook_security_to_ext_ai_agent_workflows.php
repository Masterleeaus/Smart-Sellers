<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            // Public webhook identifier (separate from internal ID)
            $table->uuid('webhook_public_id')->nullable()->unique()->after('user_id');

            // Tenant scope
            $table->unsignedBigInteger('tenant_id')->nullable()->index()->after('webhook_public_id');

            // Webhook secret reference (Credential Vault key)
            $table->string('webhook_secret_ref')->nullable()->after('tenant_id');

            // Idempotency/replay protection
            $table->json('webhook_nonces')->nullable()->after('webhook_secret_ref');

            // Index for webhook lookups
            $table->index(['webhook_public_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_agent_workflows', function (Blueprint $table) {
            $table->dropUnique(['webhook_public_id']);
            $table->dropIndex(['webhook_public_id', 'tenant_id']);
            $table->dropColumn([
                'webhook_public_id',
                'tenant_id',
                'webhook_secret_ref',
                'webhook_nonces',
            ]);
        });
    }
};
