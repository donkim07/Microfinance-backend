<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deduction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'loan_id',
        'financial_service_provider_id',
        'deduction_code',
        'deduction_description',
        'loan_number',
        'deduction_amount',
        'balance_amount',
        'check_date',
        'has_stop_pay',
        'stop_pay_reason',
        'stop_date',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'deduction_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'check_date' => 'date',
        'stop_date' => 'date',
        'has_stop_pay' => 'boolean',
    ];

    /**
     * Get the user that owns the deduction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loan that owns the deduction.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the financial service provider that owns the deduction.
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class);
    }
}
