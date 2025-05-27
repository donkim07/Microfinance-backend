<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanRepayment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'loan_id',
        'user_id',
        'loan_number',
        'payment_reference',
        'payment_amount',
        'loan_balance',
        'payment_type',
        'payment_method',
        'status',
        'description',
        'failure_reason',
        'paid_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_amount' => 'decimal:2',
        'loan_balance' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the loan that owns the repayment.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user that owns the repayment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
