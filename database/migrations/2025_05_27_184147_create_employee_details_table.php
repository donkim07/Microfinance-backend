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
        Schema::create('employee_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('check_number', 20)->unique();
            $table->string('nin', 22)->nullable()->unique();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->decimal('one_third_amount', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->integer('retirement_date')->nullable();
            $table->enum('terms_of_employment', ['Permanent and Pensionable', 'Contract'])->default('Permanent and Pensionable');
            $table->date('employment_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            $table->enum('funding', ['OS', 'Other'])->default('OS');
            $table->string('bank_account_number', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch_name', 50)->nullable();
            $table->string('bank_branch_code', 50)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_details');
    }
};
