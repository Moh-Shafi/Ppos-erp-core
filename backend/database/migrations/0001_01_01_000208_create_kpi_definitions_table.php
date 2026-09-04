<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_id', 100)->unique();
            $table->string('name', 255);
            $table->string('category', 100);
            $table->json('allowed_filters')->nullable();
            $table->string('value_format', 50)->nullable(); // currency, number, percent
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_definitions');
    }
};
