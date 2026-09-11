<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single opening of a product page.
 *
 * No updated_at, because a view is never edited -- it happened, at a time, and
 * that is the whole row. Nothing identifies the visitor; see the migration for
 * why.
 */
class ProductView extends Model
{
    protected $table = 'product_views';

    /** viewed_at is the only timestamp, and it is written by hand. */
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
