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
        DB::statement("ALTER TABLE bills MODIFY status ENUM('pending','processing','done','failed','duplicate','invalid','review') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE bills SET status='failed' WHERE status IN ('duplicate','invalid','review')");
        DB::statement("ALTER TABLE bills MODIFY status ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending'");
    }
};
