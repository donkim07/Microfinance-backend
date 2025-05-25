<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanApplication extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'application_number',
        'employee_id',
        'loan_product_id',
        'loan_type',
        'requested_amount',
        'desired_deductible_amount',
        'tenure',
        'interest_rate',
        'processing_fee',
        'insurance',
        'loan_purpose',
        'fsp_reference_number',
        'loan_number',
        'total_amount_to_pay',
        'other_charges',
        'status',
        'rejection_reason',
        'settlement_amount',
        'old_loan_number',
        'disbursement_date',
        'fsp_approval_date',
        'employer_approval_date',
        'fsp1_code',
        'fsp1_loan_number',
        'takeover_amount',
        'fsp1_bank_account',
        'fsp1_bank_account_name',
        'fsp1_swift_code',
        'fsp1_payment_reference',
        'fsp1_final_payment_date'
    ];

    protected $casts = [
        'disbursement_date' => 'datetime',
        'fsp_approval_date' => 'datetime',
        'employer_approval_date' => 'datetime',
        'fsp1_final_payment_date' => 'datetime',
    ];

    /**
     * Get the employee that owns the loan application
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the loan product for the application
     */
    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * Get the repayments for the loan application
     */
    public function repayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * Get the deductions for the loan application
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Get the FSP through the loan product
     */
    public function financialServiceProvider()
    {
        return $this->loanProduct->financialServiceProvider();
    }
}
