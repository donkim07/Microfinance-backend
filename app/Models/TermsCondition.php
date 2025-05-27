<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TermsCondition extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_catalog_id',
        'terms_condition_number',
        'description',
        'tc_effective_date',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tc_effective_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product catalog that owns the terms and conditions.
     */
    public function productCatalog()
    {
        return $this->belongsTo(ProductCatalog::class);
    }
}
