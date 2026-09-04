<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Integration providers (system-level registry)
        Schema::create('integration_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        // Tenant integrations (per-tenant provider configuration)
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integration_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('config')->nullable();
            $table->text('encrypted_credentials')->nullable();
            $table->string('status')->default('inactive'); // inactive, active, error, suspended
            $table->timestamp('last_connected_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'integration_provider_id']);
            $table->index(['tenant_id', 'status']);
        });

        // Webhook events registry (system-defined available events)
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module', 50)->nullable();
            $table->json('payload_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Webhook endpoints (tenant-configured receivers)
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('secret', 100);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        // Webhook event subscriptions (which events each endpoint receives)
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->timestamps();

            $table->unique(['webhook_endpoint_id', 'event_type']);
        });

        // Webhook deliveries (individual delivery attempts)
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->uuid('event_id');
            $table->json('payload');
            $table->string('signature');
            $table->string('status')->default('pending'); // pending, delivered, failed, dead_lettered, replayed
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->json('request_headers')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->foreignId('original_delivery_id')->nullable()->constrained('webhook_deliveries')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'last_attempt_at']);
            $table->index(['webhook_endpoint_id', 'event_id']);
        });

        // Integration API keys (for external system authentication)
        Schema::create('integration_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('key_prefix', 20);
            $table->json('scopes');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_revoked')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'is_revoked']);
        });

        // Integration logs (audit trail for outbound/inbound calls)
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_integration_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('direction', 10); // outbound, inbound
            $table->string('method', 10);
            $table->text('url');
            $table->json('request_headers')->nullable();
            $table->text('request_body')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_integration_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('integration_api_keys');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('tenant_integrations');
        Schema::dropIfExists('integration_providers');
    }
};
