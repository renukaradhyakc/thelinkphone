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
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            $table->string('locationable_type');
            $table->unsignedBigInteger('locationable_id');

            $table->unsignedTinyInteger('location_type');

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->decimal('accuracy', 10, 2)->nullable();

            $table->text('address')->nullable();
            $table->timestamps();

            $table->index(
                ['locationable_type', 'locationable_id'],
                'locations_locationable_index'
            );

            $table->index('location_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
