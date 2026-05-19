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
        if (!Schema::hasColumn('bill_logs', 'step')) {
            Schema::table('bill_logs', function (Blueprint $table) {
                $table->string('step')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bill_logs', 'step')) {
            Schema::table('bill_logs', function (Blueprint $table) {
                $table->dropColumn('step');
            });
        }
    }
};
