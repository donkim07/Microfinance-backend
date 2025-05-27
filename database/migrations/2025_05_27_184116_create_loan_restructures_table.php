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
        Schema::create('loan_restructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('outstanding_balance', 15, 2);
            $table->decimal('new_monthly_payment', 15, 2);
            $table->integer('old_tenure');
            $table->integer('new_tenure');
            $table->decimal('old_interest_rate', 5, 2);
            $table->decimal('new_interest_rate', 5, 2);
            $table->decimal('restructuring_fee', 15, 2)->nullable();
            $table->enum('restructure_type', ['TENURE_EXTENSION', 'PAYMENT_REDUCTION'])->default('TENURE_EXTENSION');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'COMPLETED'])->default('PENDING');
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
        Schema::dropIfExists('loan_restructures');
    }
};
