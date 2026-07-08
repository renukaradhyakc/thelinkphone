<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_schedules', function (Blueprint $table) {
            // Mirrors how event_id already discriminates one-off custom
            // timing rows for events. A custom (non-library) phone schedule
            // writes its day/time rows here with phone_schedule_id set and
            // event_id left null — same row shape, same table, new owner type.
            $table->unsignedBigInteger('phone_schedule_id')->nullable()->after('event_id');

            $table->foreign('phone_schedule_id')
                ->references('id')->on('phone_schedules')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_schedules', function (Blueprint $table) {
            $table->dropForeign(['phone_schedule_id']);
            $table->dropColumn('phone_schedule_id');
        });
    }
};