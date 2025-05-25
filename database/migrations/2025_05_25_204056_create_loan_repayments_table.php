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
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained();
            $table->decimal('payment_amount', 15, 2);
            $table->string('payment_reference', 30);
            $table->string('payment_description', 255)->nullable();
            $table->timestamp('payment_date');
            $table->decimal('loan_balance', 15, 2);
            $table->string('payment_type', 20); // FULL_PAYMENT, PARTIAL_PAYMENT, MONTHLY_DEDUCTION
            $table->string('payment_intention', 50)->nullable(); // reduce_tenure, reduce_installment
            $table->date('maturity_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
