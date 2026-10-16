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
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('platform', 10); // android | ios | web
            $table->text('token');
            $table->char('token_hash', 64)->unique(); // sha256 of token; one row per token
            $table->string('device_id', 64)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->json('web_keys')->nullable(); // browser push subscription keys
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->index(['user_id', 'revoked_at']);
            $table->index(['user_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
