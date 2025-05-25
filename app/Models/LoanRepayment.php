<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanRepayment extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'loan_application_id',
        'payment_amount',
        'payment_reference',
        'payment_description',
        'payment_date',
        'loan_balance',
        'payment_type',
        'payment_intention',
        'maturity_date'
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'maturity_date' => 'date',
    ];

    /**
     * Get the loan application that owns the repayment
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
