<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialServiceProvider extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fsp_code',
        'name',
        'deduction_code',
        'contact_person',
        'phone_number',
        'email',
        'address',
        'website',
        'logo',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the product catalogs for the financial service provider.
     */
    public function productCatalogs()
    {
        return $this->hasMany(ProductCatalog::class);
    }

    /**
     * Get the loan applications for the financial service provider.
     */
    public function loanApplications()
    {
        return $this->hasMany(LoanApplication::class);
    }

    /**
     * Get the loans for the financial service provider.
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Get the loan defaults for the financial service provider.
     */
    public function loanDefaults()
    {
        return $this->hasMany(LoanDefault::class);
    }

    /**
     * Get the deductions for the financial service provider.
     */
    public function deductions()
    {
        return $this->hasMany(Deduction::class);
    }

    /**
     * Get the old loan takeovers for the financial service provider.
     */
    public function oldLoanTakeovers()
    {
        return $this->hasMany(LoanTakeover::class, 'old_fsp_id');
    }

    /**
     * Get the new loan takeovers for the financial service provider.
     */
    public function newLoanTakeovers()
    {
        return $this->hasMany(LoanTakeover::class, 'new_fsp_id');
    }
}
