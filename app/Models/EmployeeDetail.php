<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDetail extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'check_number',
        'nin',
        'institution_id',
        'department_id',
        'designation_id',
        'basic_salary',
        'net_salary',
        'one_third_amount',
        'total_deductions',
        'retirement_date',
        'terms_of_employment',
        'employment_date',
        'confirmation_date',
        'contract_start_date',
        'contract_end_date',
        'marital_status',
        'funding',
        'bank_account_number',
        'bank_name',
        'bank_branch_name',
        'bank_branch_code',
        'swift_code',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'basic_salary' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'one_third_amount' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'retirement_date' => 'integer',
        'employment_date' => 'date',
        'confirmation_date' => 'date',
        'contract_start_date' => 'date',
        'contract_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the employee details.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the institution that owns the employee details.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Get the department that owns the employee details.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the designation that owns the employee details.
     */
    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
}
