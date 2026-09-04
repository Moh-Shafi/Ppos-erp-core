<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 15, 2)->nullable()->after('notes');
            $table->decimal('outstanding_balance', 15, 2)->default(0)->after('credit_limit');
            $table->foreignId('price_list_id')->nullable()->after('outstanding_balance')
                ->constrained('price_lists')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['price_list_id']);
            $table->dropColumn(['credit_limit', 'outstanding_balance', 'price_list_id']);
        });
    }
};
