<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fraud_logs', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('bill_id')
                ->constrained()
                ->nullOnDelete();

            $table->index('user_id', 'fraud_logs_user_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fraud_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('fraud_logs_user_id_index');
            $table->dropColumn('user_id');
        });
    }
};
