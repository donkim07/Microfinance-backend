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
        Schema::create('product_terms_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_product_id')->constrained()->onDelete('cascade');
            $table->string('terms_condition_number', 20);
            $table->text('description');
            $table->date('tc_effective_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_terms_conditions');
    }
};
