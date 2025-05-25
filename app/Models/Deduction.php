<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deduction extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'employee_id',
        'loan_application_id',
        'deduction_code',
        'deduction_name',
        'deduction_amount',
        'balance_amount',
        'check_date',
        'has_stop_pay',
        'stop_pay_reason',
        'stop_date',
        'is_active'
    ];

    protected $casts = [
        'check_date' => 'date',
        'stop_date' => 'date',
        'has_stop_pay' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employee that owns the deduction
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the loan application that the deduction is for
     */
    public function loanApplication()
    {
        return $this->belongsTo(LoanApplication::class);
    }
}
