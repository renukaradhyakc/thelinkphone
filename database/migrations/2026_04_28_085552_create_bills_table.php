<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('file_url');

            $table->enum('status', ['pending', 'processing', 'done', 'failed'])
                  ->default('pending')
                  ->index();

            $table->string('invoice_number')->nullable()->index();
            $table->string('vendor_name')->nullable()->index();

            $table->decimal('amount', 10, 2)->nullable();
            $table->date('bill_date')->nullable();

            $table->longText('raw_text')->nullable();

            // IMPORTANT: hash for duplicate detection
            $table->string('hash', 64)->unique();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            // PERFORMANCE INDEXES
            $table->index(['user_id', 'status']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
