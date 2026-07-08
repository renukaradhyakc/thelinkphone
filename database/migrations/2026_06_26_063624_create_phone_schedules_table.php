<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // Stores the application's canonical normalized phone number.
            //
            // Current implementation:
            //     last 10 digits (India)
            //
            // Reserved length (16) allows future migration
            // to full E.164 format without another schema change.
            $table->string('phone_number_normalized', 16);

            $table->unsignedBigInteger('schedule_id')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('schedules')->onDelete('set null');

            $table->unique(['user_id', 'phone_number_normalized'], 'uniq_user_phone_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_schedules');
    }
};