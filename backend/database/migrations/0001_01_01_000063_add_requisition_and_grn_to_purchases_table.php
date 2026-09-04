<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('requisition_id')->nullable()->after('notes')
                ->constrained('purchase_requisitions')->nullOnDelete();
            $table->foreignId('grn_id')->nullable()->after('requisition_id')
                ->constrained('goods_receipt_notes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['requisition_id']);
            $table->dropForeign(['grn_id']);
            $table->dropColumn(['requisition_id', 'grn_id']);
        });
    }
};
