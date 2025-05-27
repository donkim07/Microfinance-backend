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
        // Add foreign keys for loans after all tables exist
        Schema::table('loans', function (Blueprint $table) {
            $table->foreign('loan_application_id')->references('id')->on('loan_applications')->onDelete('cascade');
        });

        // Add foreign keys for loan_takeovers
        Schema::table('loan_takeovers', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_takeovers', 'new_loan_id')) {
                $table->foreignId('new_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            } else {
                $table->foreign('new_loan_id')->references('id')->on('loans')->nullOnDelete();
            }
        });

        // Add foreign keys for employee_details
        Schema::table('employee_details', function (Blueprint $table) {
            if (Schema::hasTable('institutions')) {
                $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            }
            
            if (Schema::hasTable('departments')) {
                $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            }
            
            if (Schema::hasTable('designations')) {
                $table->foreign('designation_id')->references('id')->on('designations')->nullOnDelete();
            }
        });

        // Add foreign key for designations table
        Schema::table('designations', function (Blueprint $table) {
            if (Schema::hasTable('job_classes')) {
                $table->foreign('job_class_id')->references('id')->on('job_classes')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign keys from loans
        Schema::table('loans', function (Blueprint $table) {
            $table->dropForeign(['loan_application_id']);
        });

        // Remove foreign keys from loan_takeovers
        Schema::table('loan_takeovers', function (Blueprint $table) {
            $table->dropForeign(['new_loan_id']);
        });

        // Remove foreign keys from employee_details
        Schema::table('employee_details', function (Blueprint $table) {
            if (Schema::hasTable('institutions')) {
                $table->dropForeign(['institution_id']);
            }
            
            if (Schema::hasTable('departments')) {
                $table->dropForeign(['department_id']);
            }
            
            if (Schema::hasTable('designations')) {
                $table->dropForeign(['designation_id']);
            }
        });

        // Remove foreign key from designations table
        Schema::table('designations', function (Blueprint $table) {
            if (Schema::hasTable('job_classes')) {
                $table->dropForeign(['job_class_id']);
            }
        });
    }
};
