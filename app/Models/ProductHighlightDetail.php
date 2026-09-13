<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One product inside a highlight. The pairing and nothing else.
 */
class ProductHighlightDetail extends Model
{
    protected $table = 'product_highlight_details';

    // Table only has created_at (no updated_at), as package_details.
    public $timestamps = false;

    protected $fillable = [
        'product_highlight_id',
        'product_id',
    ];

    public function highlight()
    {
        return $this->belongsTo(ProductHighlight::class, 'product_highlight_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Human label for the line, whatever became of the product.
     *
     * A product deleted outside ProductService::delete() -- straight from the
     * database, say -- would otherwise render as a blank row, which reads as a
     * bug rather than as the missing product it is.
     */
    public function getLineLabelAttribute(): string
    {
        return optional($this->product)->products_name ?: '(deleted product)';
    }
}
