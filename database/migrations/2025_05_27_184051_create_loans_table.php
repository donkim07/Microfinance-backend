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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number', 20)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('financial_service_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_catalog_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('loan_application_id');
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2);
            $table->decimal('disbursed_amount', 15, 2)->nullable();
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('processing_fee', 5, 2)->nullable();
            $table->decimal('insurance', 5, 2)->nullable();
            $table->decimal('other_charges', 15, 2)->nullable();
            $table->decimal('total_amount_to_pay', 15, 2);
            $table->decimal('balance', 15, 2);
            $table->decimal('outstanding_amount', 15, 2)->default(0);
            $table->integer('tenure');
            $table->string('fsp_reference_number', 20)->nullable();
            $table->string('deduction_code', 10)->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'DISBURSED', 'ACTIVE', 'COMPLETED', 'CANCELLED', 'DEFAULTED'])->default('PENDING');
            $table->enum('loan_type', ['NEW', 'TOP_UP', 'RESTRUCTURED', 'TAKEOVER'])->default('NEW');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('last_payment_date')->nullable();
            $table->string('purpose')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
