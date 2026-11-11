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
        Schema::create('revenuecat_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 100)->unique();
            $table->string('type', 40);
            $table->string('app_user_id', 191)->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // matched CallALink user, if any
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
 
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenuecat_events');
    }
};
