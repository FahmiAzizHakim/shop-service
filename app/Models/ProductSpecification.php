<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpecification extends Model
{
    protected $table = 'product_specifications';

    // Table only has created_at (no updated_at).
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'attribute',
        'value',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
