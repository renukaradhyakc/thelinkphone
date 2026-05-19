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
        Schema::create('fraud_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();

            $table->integer('score');
            $table->json('reasons')->nullable();

            $table->enum('decision', ['approved', 'review', 'rejected']);

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('override_status', ['approved', 'rejected'])->nullable();

            $table->timestamps();

            $table->index(['bill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_logs');
    }
};
