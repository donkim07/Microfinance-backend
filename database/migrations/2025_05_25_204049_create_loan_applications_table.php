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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 15)->unique();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('loan_product_id')->constrained();
            $table->string('loan_type', 20); // NEW_LOAN, LOAN_TOP_UP, LOAN_TAKEOVER, LOAN_RESTRUCTURING
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('desired_deductible_amount', 15, 2);
            $table->unsignedSmallInteger('tenure');
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('processing_fee', 5, 2)->nullable();
            $table->decimal('insurance', 5, 2);
            $table->text('loan_purpose');
            $table->string('fsp_reference_number', 20)->nullable();
            $table->string('loan_number', 20)->nullable();
            $table->decimal('total_amount_to_pay', 15, 2)->nullable();
            $table->decimal('other_charges', 15, 2)->nullable();
            $table->string('status', 30); // INITIATED, LOAN_OFFER_AT_FSP, FSP_REJECTED, LOAN_OFFER_AT_EMPLOYEE, etc.
            $table->text('rejection_reason')->nullable();
            $table->decimal('settlement_amount', 15, 2)->nullable(); // For loan top-up
            $table->string('old_loan_number', 20)->nullable(); // For loan top-up
            $table->timestamp('disbursement_date')->nullable();
            $table->timestamp('fsp_approval_date')->nullable();
            $table->timestamp('employer_approval_date')->nullable();
            $table->string('fsp1_code', 10)->nullable(); // For takeover
            $table->string('fsp1_loan_number', 20)->nullable(); // For takeover
            $table->decimal('takeover_amount', 15, 2)->nullable(); // For takeover
            $table->string('fsp1_bank_account', 20)->nullable(); // For takeover
            $table->string('fsp1_bank_account_name', 50)->nullable(); // For takeover
            $table->string('fsp1_swift_code', 12)->nullable(); // For takeover
            $table->string('fsp1_payment_reference', 30)->nullable(); // For takeover
            $table->timestamp('fsp1_final_payment_date')->nullable(); // For takeover
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
