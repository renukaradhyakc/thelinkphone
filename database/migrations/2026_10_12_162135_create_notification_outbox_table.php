<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scheduled sends (reminders, location reminder, live-location auto-stop).
     * notify_at is always written and compared in UTC.
     */
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // recipient
            $table->unsignedBigInteger('event_schedule_id')->nullable(); // the booking
            $table->string('type', 40);
            $table->string('reason', 40);
            $table->dateTime('notify_at'); // UTC
            $table->string('dedupe_key', 150)->unique();
            $table->string('status', 12)->default('pending'); // pending | claimed | sent | cancelled | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('claimed_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')
                ->onUpdate('cascade')->onDelete('cascade');
            // deleting a booking (or its event) removes its pending reminders
            $table->foreign('event_schedule_id')->references('id')->on('event_schedules')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->index(['status', 'notify_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
