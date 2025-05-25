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
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('loan_application_id')->constrained();
            $table->string('deduction_code', 10);
            $table->string('deduction_name', 255);
            $table->decimal('deduction_amount', 15, 2);
            $table->decimal('balance_amount', 15, 2);
            $table->date('check_date');
            $table->boolean('has_stop_pay')->default(false);
            $table->string('stop_pay_reason', 255)->nullable();
            $table->date('stop_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
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
