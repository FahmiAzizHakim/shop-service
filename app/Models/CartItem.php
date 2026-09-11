<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'item_key',
        'item_type',
        'product_id',
        'product_variant_id',
        'package_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }
}
