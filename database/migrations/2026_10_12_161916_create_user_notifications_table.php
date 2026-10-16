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
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 40);
            $table->string('reason', 40);
            $table->string('category', 30);
            $table->string('title');
            $table->text('body');
            $table->json('data')->nullable();
            $table->string('deep_link')->nullable();
            $table->string('dedupe_key', 150);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
