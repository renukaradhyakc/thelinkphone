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
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();

            // Identity
            $table->string('description')->nullable();
            $table->text('raw_description')->nullable();

            $table->string('brand')->nullable();

            $table->string('category')->nullable();
            $table->json('categories')->nullable();

            $table->string('item_type')->nullable();
            $table->string('line_type')->nullable();

            $table->string('hsn_code')->nullable();
            $table->string('sku')->nullable();
            $table->string('upc')->nullable();
            $table->string('unit')->nullable();

            $table->string('provider',50)->nullable()->index();        

            // Pricing
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('line_total', 10, 2);
            $table->decimal('computed_total', 10, 2)->nullable();

            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('tax_amount', 10, 2)->nullable();

            // Loyalty
            $table->boolean('is_eligible')->default(true);
            $table->integer('points_earned')->default(0);

            // Meta
            $table->float('confidence')->nullable();
            $table->json('raw_meta')->nullable();

            // Ordering
            $table->integer('position')->nullable();

            $table->timestamps();

            $table->index('bill_id');
            $table->index('category');
            $table->index('hsn_code');
            $table->index('is_eligible');
            $table->index(['bill_id', 'is_eligible']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
