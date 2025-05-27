<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanTakeover extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'old_fsp_id',
        'new_fsp_id',
        'loan_application_id',
        'new_loan_id',
        'old_loan_number',
        'new_loan_number',
        'outstanding_balance',
        'takeover_amount',
        'new_monthly_payment',
        'tenure',
        'interest_rate',
        'status',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'outstanding_balance' => 'decimal:2',
        'takeover_amount' => 'decimal:2',
        'new_monthly_payment' => 'decimal:2',
        'tenure' => 'integer',
        'interest_rate' => 'decimal:2',
    ];

    /**
     * Get the user who requested the takeover.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the old financial service provider.
     */
    public function oldFsp()
    {
        return $this->belongsTo(FinancialServiceProvider::class, 'old_fsp_id');
    }

    /**
     * Get the new financial service provider.
     */
    public function newFsp()
    {
        return $this->belongsTo(FinancialServiceProvider::class, 'new_fsp_id');
    }

    /**
     * Get the loan application for the takeover.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Get the new loan created for the takeover.
     */
    public function newLoan()
    {
        return $this->belongsTo(Loan::class, 'new_loan_id');
    }
}
