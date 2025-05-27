<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanDisbursement extends Model
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
        'disbursed_amount',
        'total_amount_to_pay',
        'fsp_reference_number',
        'status',
        'failure_reason',
        'disbursed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'disbursed_amount' => 'decimal:2',
        'total_amount_to_pay' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    /**
     * Get the loan that owns the disbursement.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the loan application that owns the disbursement.
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
