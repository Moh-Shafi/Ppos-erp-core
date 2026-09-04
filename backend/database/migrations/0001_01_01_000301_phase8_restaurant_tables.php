<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Floor areas (sections of the restaurant)
        Schema::create('table_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_id');
            $table->string('name', 100);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'store_id']);
        });

        // Tables (physical restaurant tables)
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('table_area_id');
            $table->string('name', 50);
            $table->string('code', 20);
            $table->integer('capacity')->default(4);
            $table->enum('status', ['available', 'occupied', 'reserved', 'cleaning'])->default('available');
            $table->string('qr_code', 100)->nullable()->unique();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id', 'status']);
            $table->foreign('table_area_id')->references('id')->on('table_areas')->cascadeOnDelete();
        });

        // Reservations
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->date('reservation_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('party_size');
            $table->enum('status', ['pending', 'confirmed', 'seated', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'store_id', 'reservation_date', 'status']);
            $table->foreign('table_id')->references('id')->on('tables')->nullOnDelete();
        });

        // Kitchen Order Tickets
        Schema::create('kot_headers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('kot_number', 30);
            $table->enum('status', ['new', 'preparing', 'ready', 'served', 'cancelled'])->default('new');
            $table->enum('priority', ['normal', 'rush'])->default('normal');
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['tenant_id', 'store_id', 'status', 'created_at']);
        });

        // KOT items
        Schema::create('kot_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kot_header_id');
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->json('modifiers')->nullable();
            $table->string('notes', 255)->nullable();
            $table->enum('status', ['queued', 'preparing', 'ready', 'served'])->default('queued');
            $table->timestamps();

            $table->foreign('kot_header_id')->references('id')->on('kot_headers')->cascadeOnDelete();
        });

        // Modifiers
        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name', 100);
            $table->enum('type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        // Modifier options
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('modifier_id');
            $table->string('name', 100);
            $table->decimal('price_delta', 15, 2)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('modifier_id')->references('id')->on('modifiers')->cascadeOnDelete();
        });

        // Product-modifier mapping
        Schema::create('product_modifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('modifier_id');
            $table->timestamps();

            $table->unique(['product_id', 'modifier_id']);
            $table->foreign('modifier_id')->references('id')->on('modifiers')->cascadeOnDelete();
        });

        // Recipes
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('yield_quantity', 15, 3)->default(1);
            $table->unsignedBigInteger('yield_unit_id')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        // Recipe ingredients
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('ingredient_product_id');
            $table->decimal('quantity', 15, 3);
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
            $table->foreign('ingredient_product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        // Bill splits
        Schema::create('bill_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sale_id');
            $table->enum('split_type', ['equal', 'per_item', 'per_person', 'custom']);
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
        });

        // Bill split items
        Schema::create('bill_split_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_split_id');
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->foreign('bill_split_id')->references('id')->on('bill_splits')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_split_items');
        Schema::dropIfExists('bill_splits');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('product_modifiers');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('kot_items');
        Schema::dropIfExists('kot_headers');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('tables');
        Schema::dropIfExists('table_areas');
    }
};
