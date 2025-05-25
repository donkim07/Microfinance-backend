<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialServiceProvider extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'fsp_code',
        'fsp_name',
        'public_key_certificate',
        'is_active'
    ];

    /**
     * Get the loan products for the FSP
     */
    public function loanProducts()
    {
        return $this->hasMany(LoanProduct::class, 'fsp_id');
    }
}
