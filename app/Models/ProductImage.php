<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';

    // Table only has created_at (no updated_at).
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'image_name',
        'image_url',
        'image_description',
        'order',
        'is_active',
        'created_by',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
