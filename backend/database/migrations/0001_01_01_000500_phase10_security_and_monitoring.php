<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two-Factor Authentication
        Schema::create('two_factor_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('secret');
            $table->json('backup_codes')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        // Password History
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash', 255);
            $table->timestamp('created_at');

            $table->index('user_id');
        });

        // Account Lockouts
        Schema::create('account_lockouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 255);
            $table->integer('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('username');
            $table->index('locked_until');
        });

        // Data Retention Policies
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('data_type', 50);
            $table->integer('retention_days')->default(90);
            $table->boolean('auto_purge')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'data_type']);
        });

        // Add 2FA columns to users
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false)->after('remember_token');
            $table->timestamp('two_factor_verified_at')->nullable()->after('two_factor_enabled');
            $table->softDeletes('deleted_at')->after('two_factor_verified_at');
        });

        // Add route and method to audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('route')->nullable()->after('user_agent');
            $table->string('method')->nullable()->after('route');
        });

        // Add indexes safely (check if they already exist)
        try {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'created_at'], 'audit_logs_tenant_id_created_at_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        try {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_type_entity_id_index');
            });
        } catch (\Exception $e) {
            // Index already exists
        }

        // Performance indexes on hot tables
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'store_id', 'status'], 'sales_tenant_store_status_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active', 'category_id'], 'products_tenant_active_category_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'product_id', 'created_at'], 'inv_mov_tenant_product_created_idx');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'created_at'], 'webhook_del_tenant_status_created_idx');
        });

        Schema::table('integration_api_keys', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_revoked'], 'intg_keys_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('integration_api_keys', function (Blueprint $table) {
            $table->dropIndex('intg_keys_tenant_active_idx');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('webhook_del_tenant_status_created_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inv_mov_tenant_product_created_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_active_category_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_store_status_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_entity_type_entity_id_index');
            $table->dropIndex('audit_logs_tenant_id_created_at_index');
            $table->dropColumn(['route', 'method']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_verified_at', 'deleted_at']);
        });

        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('account_lockouts');
        Schema::dropIfExists('password_histories');
        Schema::dropIfExists('two_factor_auth');
    }
};
