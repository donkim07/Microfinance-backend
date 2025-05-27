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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id')->nullable();
            $table->string('middle_name')->after('first_name')->nullable();
            $table->string('last_name')->after('middle_name')->nullable();
            $table->enum('gender', ['M', 'F'])->after('name')->nullable();
            $table->string('phone_number', 15)->after('email')->nullable();
            $table->string('address')->after('phone_number')->nullable();
            $table->string('profile_photo')->after('address')->nullable();
            $table->string('locale', 5)->after('profile_photo')->default('en');
            $table->foreignId('financial_service_provider_id')->after('locale')->nullable()->constrained()->nullOnDelete();
            $table->string('check_number', 20)->after('financial_service_provider_id')->nullable();
            $table->boolean('is_active')->after('check_number')->default(true);
            $table->timestamp('last_login_at')->after('is_active')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['financial_service_provider_id']);
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'phone_number',
                'address',
                'profile_photo',
                'locale',
                'financial_service_provider_id',
                'check_number',
                'is_active',
                'last_login_at',
                'deleted_at'
            ]);
        });
    }
};
