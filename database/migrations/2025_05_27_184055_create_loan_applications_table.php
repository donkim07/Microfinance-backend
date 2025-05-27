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
            $table->string('application_number', 20)->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('financial_service_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_catalog_id')->constrained()->onDelete('cascade');
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('desired_deductible_amount', 15, 2);
            $table->integer('tenure');
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('processing_fee', 5, 2)->nullable();
            $table->decimal('insurance', 5, 2)->nullable();
            $table->decimal('other_charges', 15, 2)->nullable();
            $table->decimal('total_amount_to_pay', 15, 2)->nullable();
            $table->string('fsp_reference_number', 20)->nullable();
            $table->string('loan_number', 20)->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'])->default('PENDING');
            $table->enum('application_type', ['NEW', 'TOP_UP', 'RESTRUCTURED', 'TAKEOVER'])->default('NEW');
            $table->string('purpose')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
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
