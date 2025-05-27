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
        Schema::create('loan_takeovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('old_fsp_id')->constrained('financial_service_providers')->onDelete('cascade');
            $table->foreignId('new_fsp_id')->constrained('financial_service_providers')->onDelete('cascade');
            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('new_loan_id')->nullable();
            $table->string('old_loan_number', 20);
            $table->string('new_loan_number', 20)->nullable();
            $table->decimal('outstanding_balance', 15, 2);
            $table->decimal('takeover_amount', 15, 2);
            $table->decimal('new_monthly_payment', 15, 2);
            $table->integer('tenure');
            $table->decimal('interest_rate', 5, 2);
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_takeovers');
    }
};
