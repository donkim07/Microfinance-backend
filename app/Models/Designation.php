<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Designation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_class_id',
        'designation_code',
        'designation_name',
        'description',
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
     * Get the job class that owns the designation.
     */
    public function jobClass()
    {
        return $this->belongsTo(JobClass::class);
    }

    /**
     * Get the employee details for the designation.
     */
    public function employeeDetails()
    {
        return $this->hasMany(EmployeeDetail::class);
    }
}
