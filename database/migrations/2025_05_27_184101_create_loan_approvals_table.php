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
        Schema::create('loan_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('approval_level', ['INITIAL', 'FINAL'])->default('INITIAL');
            $table->enum('status', ['APPROVED', 'REJECTED'])->default('APPROVED');
            $table->text('remarks')->nullable();
            $table->string('fsp_reference_number', 20)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_approvals');
    }
};
