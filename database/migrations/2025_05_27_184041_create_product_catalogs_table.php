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
        Schema::create('product_catalogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_service_provider_id')->constrained()->onDelete('cascade');
            $table->string('product_code', 8)->unique();
            $table->string('product_name', 255);
            $table->string('product_description', 255)->nullable();
            $table->integer('minimum_tenure');
            $table->integer('maximum_tenure');
            $table->decimal('interest_rate', 5, 2);
            $table->decimal('processing_fee', 5, 2)->nullable();
            $table->decimal('insurance', 5, 2);
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->string('repayment_type', 10)->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->enum('insurance_type', ['DISTRIBUTED', 'UP_FRONT'])->default('DISTRIBUTED');
            $table->boolean('for_executive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_catalogs');
    }
};
