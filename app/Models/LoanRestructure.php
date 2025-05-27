<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRestructure extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'loan_id',
        'loan_application_id',
        'user_id',
        'outstanding_balance',
        'new_monthly_payment',
        'old_tenure',
        'new_tenure',
        'old_interest_rate',
        'new_interest_rate',
        'restructuring_fee',
        'restructure_type',
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
        'new_monthly_payment' => 'decimal:2',
        'old_tenure' => 'integer',
        'new_tenure' => 'integer',
        'old_interest_rate' => 'decimal:2',
        'new_interest_rate' => 'decimal:2',
        'restructuring_fee' => 'decimal:2',
    ];

    /**
     * Get the loan that is being restructured.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the loan application for the restructure.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }

    /**
     * Get the user who requested the restructure.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
