<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bill_meta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();

            $table->string('key');
            $table->text('value')->nullable();

            $table->timestamps();

            $table->index(['bill_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_meta');
    }
};