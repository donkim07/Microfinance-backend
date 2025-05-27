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
        Schema::create('loan_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('financial_service_provider_id')->constrained()->onDelete('cascade');
            $table->string('loan_number', 20);
            $table->decimal('default_amount', 15, 2);
            $table->decimal('outstanding_balance', 15, 2);
            $table->integer('months_in_arrears');
            $table->date('default_date');
            $table->enum('status', ['REPORTED', 'UNDER_REVIEW', 'CONFIRMED', 'DISPUTED', 'RESOLVED'])->default('REPORTED');
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->date('resolved_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_defaults');
    }
};
