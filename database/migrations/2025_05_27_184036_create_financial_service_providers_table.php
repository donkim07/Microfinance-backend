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
        Schema::create('financial_service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('fsp_code', 10)->unique();
            $table->string('name', 100);
            $table->string('deduction_code', 10)->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone_number', 15)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_service_providers');
    }
};
