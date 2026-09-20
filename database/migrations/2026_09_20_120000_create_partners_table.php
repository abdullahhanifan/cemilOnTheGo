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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50);
            $table->string('city', 100);
            $table->string('area')->nullable();
            // 'bank' or 'e_wallet'. The payment method, account name and account number are either all
            // filled or all empty.
            $table->string('payment_method', 20)->nullable();
            // Free text, optional: the bank or e-wallet the number belongs to (BCA, Mandiri, GoPay, ...).
            $table->string('payment_provider', 100)->nullable();
            $table->string('account_name')->nullable();
            // Encrypted by the model cast. The ciphertext is much longer than the number, hence text.
            $table->text('account_number')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('active');
            // Reserved for a future partner login. Nothing reads or writes it yet.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('city');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
