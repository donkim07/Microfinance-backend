<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'fsp_id',
        'product_code',
        'product_name',
        'product_description',
        'minimum_tenure',
        'maximum_tenure',
        'interest_rate',
        'processing_fee',
        'insurance',
        'min_amount',
        'max_amount',
        'repayment_type',
        'for_executive',
        'deduction_code',
        'insurance_type',
        'currency',
        'is_active'
    ];

    /**
     * Get the FSP that owns the loan product
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class, 'fsp_id');
    }

    /**
     * Get the terms and conditions for the product
     */
    public function termsConditions()
    {
        return $this->hasMany(ProductTermCondition::class);
    }

    /**
     * Get the loan applications for this product
     */
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }
}
