<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_type_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default_enabled')->default(true);
            $table->timestamps();
            $table->unique(['business_type_id', 'module_id']);
        });

        Schema::create('business_type_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_default_enabled')->default(true);
            $table->timestamps();
            $table->unique(['business_type_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_type_features');
        Schema::dropIfExists('business_type_modules');
    }
};
