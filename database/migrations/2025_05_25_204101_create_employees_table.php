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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('check_number', 15)->unique();
            $table->string('first_name', 30);
            $table->string('middle_name', 30)->nullable();
            $table->string('last_name', 30);
            $table->char('sex', 1);
            $table->date('employment_date');
            $table->string('marital_status', 10);
            $table->date('confirmation_date')->nullable();
            $table->string('bank_account_number', 20);
            $table->string('nearest_branch_name', 50)->nullable();
            $table->string('nearest_branch_code', 50)->nullable();
            $table->string('vote_code', 6);
            $table->string('vote_name', 255);
            $table->string('nin', 22)->unique();
            $table->string('designation_code', 8);
            $table->string('designation_name', 255);
            $table->decimal('basic_salary', 15, 2);
            $table->decimal('net_salary', 15, 2);
            $table->decimal('one_third_amount', 15, 2);
            $table->integer('retirement_date');
            $table->string('terms_of_employment', 30);
            $table->string('physical_address', 50);
            $table->string('telephone_number', 12)->nullable();
            $table->string('email_address', 50);
            $table->string('mobile_number', 12);
            $table->string('job_class_code', 10)->nullable();
            $table->string('swift_code', 50)->nullable();
            $table->string('funding', 50)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->boolean('is_executive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
