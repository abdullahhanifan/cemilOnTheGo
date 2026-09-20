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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('name');
            // There is no variants table: every variant (size, flavor, ...) is its own product.
            // Blank string rather than NULL when there is none, so the unique key below
            // also rejects two products with the same name and no variant.
            $table->string('variant', 100)->default('');
            $table->string('sku', 100)->nullable();
            $table->string('unit', 30)->default('pcs');
            // Whole rupiah. Both are reference prices: buy_price is the last known price at the store,
            // sell_price the default price to the customer. Invoices snapshot them and purchases record
            // the price actually paid, so changing them here never rewrites history.
            $table->unsignedBigInteger('buy_price');
            $table->unsignedBigInteger('sell_price');
            $table->timestamp('buy_price_checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['store_id', 'name', 'variant']);
            $table->index(['store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
