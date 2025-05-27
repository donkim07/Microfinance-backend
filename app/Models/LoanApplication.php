<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanApplication extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'application_number',
        'user_id',
        'financial_service_provider_id',
        'product_catalog_id',
        'requested_amount',
        'desired_deductible_amount',
        'tenure',
        'interest_rate',
        'processing_fee',
        'insurance',
        'other_charges',
        'total_amount_to_pay',
        'fsp_reference_number',
        'loan_number',
        'status',
        'application_type',
        'purpose',
        'rejection_reason',
        'cancellation_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_amount' => 'decimal:2',
        'desired_deductible_amount' => 'decimal:2',
        'tenure' => 'integer',
        'interest_rate' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'insurance' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount_to_pay' => 'decimal:2',
    ];

    /**
     * Get the user that owns the loan application.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the financial service provider that owns the loan application.
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class);
    }

    /**
     * Get the product catalog that owns the loan application.
     */
    public function productCatalog()
    {
        return $this->belongsTo(ProductCatalog::class);
    }

    /**
     * Get the loan for the loan application.
     */
    public function loan()
    {
        return $this->hasOne(Loan::class);
    }

    /**
     * Get the loan approvals for the loan application.
     */
    public function loanApprovals()
    {
        return $this->hasMany(LoanApproval::class);
    }

    /**
     * Get the loan disbursement for the loan application.
     */
    public function loanDisbursement()
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    /**
     * Get the latest loan approval for the loan application.
     */
    public function latestLoanApproval()
    {
        return $this->hasOne(LoanApproval::class)->latestOfMany();
    }
}
