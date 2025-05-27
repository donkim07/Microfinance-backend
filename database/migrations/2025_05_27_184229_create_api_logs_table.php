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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable();
            $table->string('message_type');
            $table->string('sender');
            $table->string('receiver');
            $table->string('fsp_code', 10)->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('direction')->default('OUTGOING');
            $table->string('status_code', 10)->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('endpoint')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
