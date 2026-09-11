<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'variant_name',
        'variant_price',
        'variant_description',
        'variant_color',
        'variant_weight',
        'variant_width',
        'variant_length',
        'variant_height',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
