<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductTermCondition extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'loan_product_id',
        'terms_condition_number',
        'description',
        'tc_effective_date'
    ];

    /**
     * Get the loan product that owns the term condition
     */
    public function loanProduct()
    {
        return $this->belongsTo(LoanProduct::class);
    }
}
