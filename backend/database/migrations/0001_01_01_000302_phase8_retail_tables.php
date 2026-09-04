<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Promotions
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed', 'buy_x_get_y', 'tiered']);
            $table->decimal('value', 15, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->json('conditions')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'start_date', 'end_date']);
        });

        // Promotion rules
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            $table->enum('rule_type', ['min_purchase', 'min_quantity', 'category', 'product', 'customer_group']);
            $table->json('rule_value');
            $table->timestamps();

            $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
        });

        // Loyalty programs
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->decimal('points_per_currency', 15, 4);
            $table->decimal('currency_per_point', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Loyalty tiers
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loyalty_program_id');
            $table->string('name', 50);
            $table->integer('min_points');
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->timestamps();

            $table->foreign('loyalty_program_id')->references('id')->on('loyalty_programs')->cascadeOnDelete();
        });

        // Loyalty transactions
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('loyalty_program_id')->nullable();
            $table->enum('type', ['earn', 'redeem', 'expire', 'adjust']);
            $table->integer('points');
            $table->integer('balance_after');
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id', 'created_at']);
        });

        // Price tag templates
        Schema::create('price_tag_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->json('layout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_tag_templates');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('loyalty_programs');
        Schema::dropIfExists('promotion_rules');
        Schema::dropIfExists('promotions');
    }
};
