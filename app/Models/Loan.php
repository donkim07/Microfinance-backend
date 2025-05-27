<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Loan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'loan_number',
        'user_id',
        'financial_service_provider_id',
        'product_catalog_id',
        'loan_application_id',
        'requested_amount',
        'approved_amount',
        'disbursed_amount',
        'monthly_payment',
        'interest_rate',
        'processing_fee',
        'insurance',
        'other_charges',
        'total_amount_to_pay',
        'balance',
        'tenure',
        'fsp_reference_number',
        'deduction_code',
        'status',
        'loan_type',
        'start_date',
        'end_date',
        'last_payment_date',
        'purpose',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'disbursed_amount' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'insurance' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount_to_pay' => 'decimal:2',
        'balance' => 'decimal:2',
        'tenure' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_payment_date' => 'date',
    ];

    /**
     * Get the user that owns the loan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the financial service provider that owns the loan.
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class);
    }

    /**
     * Get the product catalog that owns the loan.
     */
    public function productCatalog()
    {
        return $this->belongsTo(ProductCatalog::class);
    }

    /**
     * Get the loan application that owns the loan.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Get the loan disbursement for the loan.
     */
    public function loanDisbursement()
    {
        return $this->hasOne(LoanDisbursement::class);
    }

    /**
     * Get the loan repayments for the loan.
     */
    public function loanRepayments()
    {
        return $this->hasMany(LoanRepayment::class);
    }

    /**
     * Get the loan restructures for the loan.
     */
    public function loanRestructures()
    {
        return $this->hasMany(LoanRestructure::class);
    }

    /**
     * Get the loan takeovers for the loan.
     */
    public function loanTakeovers()
    {
        return $this->hasMany(LoanTakeover::class, 'new_loan_id');
    }

    /**
     * Get the loan defaults for the loan.
     */
    public function loanDefaults()
    {
        return $this->hasMany(LoanDefault::class);
    }

    /**
     * Get the deductions for the loan.
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }
}
