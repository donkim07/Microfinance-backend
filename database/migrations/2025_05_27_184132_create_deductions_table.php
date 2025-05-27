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
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_service_provider_id')->constrained()->onDelete('cascade');
            $table->string('deduction_code', 10);
            $table->string('deduction_description')->nullable();
            $table->string('loan_number', 20)->nullable();
            $table->decimal('deduction_amount', 15, 2);
            $table->decimal('balance_amount', 15, 2)->nullable();
            $table->date('check_date');
            $table->boolean('has_stop_pay')->default(false);
            $table->string('stop_pay_reason')->nullable();
            $table->date('stop_date')->nullable();
            $table->enum('status', ['ACTIVE', 'STOPPED', 'COMPLETED'])->default('ACTIVE');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
