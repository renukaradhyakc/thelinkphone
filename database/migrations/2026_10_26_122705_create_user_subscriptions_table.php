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
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('store', 20)->nullable();        // APP_STORE | PLAY_STORE | ...
            $table->string('product_id', 100)->nullable();
            $table->string('period_type', 10)->nullable();  // TRIAL | INTRO | NORMAL
            $table->string('status', 20)->default('active'); // active | billing_issue | expired
            $table->boolean('will_renew')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->unsignedBigInteger('last_event_ms')->default(0); // ignores late, older events
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->index(['period_type', 'status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
