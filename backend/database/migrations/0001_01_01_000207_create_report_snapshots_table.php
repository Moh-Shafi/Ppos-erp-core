<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('report_id', 100);
            $table->string('filter_hash', 64);
            $table->json('filters');
            $table->json('result');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'report_id', 'filter_hash']);
            $table->index(['tenant_id', 'report_id', 'version']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
