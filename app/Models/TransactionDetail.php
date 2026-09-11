<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    protected $table = 'transaction_details';

    protected $fillable = [
        'transaction_id',
        'product_id',
        'product_variant_id',
        'product_code',
        'product_name',
        'variant_name',
        'uom',
        'qty',
        'price',
        'discount',
        'subtotal',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'qty'      => 'integer',
        'price'    => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
