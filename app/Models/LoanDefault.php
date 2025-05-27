<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanDefault extends Model
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
        'financial_service_provider_id',
        'loan_number',
        'default_amount',
        'outstanding_balance',
        'months_in_arrears',
        'default_date',
        'status',
        'description',
        'resolution_notes',
        'resolved_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'default_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'months_in_arrears' => 'integer',
        'default_date' => 'date',
        'resolved_date' => 'date',
    ];

    /**
     * Get the loan that defaulted.
     */
    public function loan()
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user who owns the defaulted loan.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the financial service provider for the defaulted loan.
     */
    public function financialServiceProvider()
    {
        return $this->belongsTo(FinancialServiceProvider::class);
    }
}
