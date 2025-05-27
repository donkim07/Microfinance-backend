<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCatalog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'financial_service_provider_id',
        'product_code',
        'product_name',
        'product_description',
        'minimum_tenure',
        'maximum_tenure',
        'interest_rate',
        'minimum_amount',
        'maximum_amount',
        'processing_fee',
        'insurance',
        'other_charges',
        'is_active',
        'loan_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'minimum_tenure' => 'integer',
        'maximum_tenure' => 'integer',
        'interest_rate' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'insurance' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the financial service provider that owns the product catalog.
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class);
    }

    /**
     * Get the terms and conditions for the product catalog.
     */
    public function termsConditions()
    {
        return $this->hasMany(TermsCondition::class);
    }

    /**
     * Get the loan applications for the product catalog.
     */
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * Get the loans for the product catalog.
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Get the latest terms and conditions for the product catalog.
     */
    public function latestTermsConditions()
    {
        return $this->hasOne(TermsCondition::class)->latestOfMany();
    }
}
