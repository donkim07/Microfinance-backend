<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'check_number',
        'first_name',
        'middle_name',
        'last_name',
        'sex',
        'employment_date',
        'marital_status',
        'confirmation_date',
        'bank_account_number',
        'nearest_branch_name',
        'nearest_branch_code',
        'vote_code',
        'vote_name',
        'nin',
        'designation_code',
        'designation_name',
        'basic_salary',
        'net_salary',
        'one_third_amount',
        'retirement_date',
        'terms_of_employment',
        'physical_address',
        'telephone_number',
        'email_address',
        'mobile_number',
        'job_class_code',
        'swift_code',
        'funding',
        'contract_start_date',
        'contract_end_date',
        'is_executive',
        'is_active'
    ];

    /**
     * Get the user associated with the employee
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loan applications for the employee
     */
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * Get the deductions for the employee
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Get the full name of the employee
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->middle_name} {$this->last_name}";
    }
}
