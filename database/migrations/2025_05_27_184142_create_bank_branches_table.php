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
        Schema::create('bank_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained()->onDelete('cascade');
            $table->string('branch_code', 20)->unique();
            $table->string('branch_name', 100);
            $table->string('district_code', 20)->nullable();
            $table->string('district_name', 100)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_branches');
    }
};
