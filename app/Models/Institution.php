<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vote_code',
        'vote_name',
        'address',
        'phone',
        'email',
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
     * Get the departments for the institution.
     */
    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get the employee details for the institution.
     */
    public function employeeDetails()
    {
        return $this->hasMany(EmployeeDetail::class);
    }
}
