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
        Schema::create('loan_disbursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->decimal('disbursed_amount', 15, 2);
            $table->decimal('total_amount_to_pay', 15, 2);
            $table->string('fsp_reference_number', 20)->nullable();
            $table->enum('status', ['SUCCESSFUL', 'FAILED'])->default('SUCCESSFUL');
            $table->text('failure_reason')->nullable();
            $table->timestamp('disbursed_at');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_disbursements');
    }
};
