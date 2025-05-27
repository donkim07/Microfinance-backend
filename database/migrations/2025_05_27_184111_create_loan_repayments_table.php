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
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('loan_number', 20);
            $table->string('payment_reference', 20)->nullable();
            $table->decimal('payment_amount', 15, 2);
            $table->decimal('loan_balance', 15, 2);
            $table->enum('payment_type', ['MONTHLY_DEDUCTION', 'FULL_PAYMENT', 'PARTIAL_PAYMENT'])->default('MONTHLY_DEDUCTION');
            $table->enum('payment_method', ['BANK_TRANSFER', 'MOBILE_MONEY', 'CASH', 'DEDUCTION'])->default('DEDUCTION');
            $table->enum('status', ['PENDING', 'SUCCESSFUL', 'FAILED'])->default('SUCCESSFUL');
            $table->text('description')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->softDeletes();
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
