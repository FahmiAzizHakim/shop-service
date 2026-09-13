<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A named, hand-picked set of products: "Best Seller", "New Arrivals".
 *
 * The name is the heading the storefront prints; the details are the products
 * under it. Nothing about a product is stored here -- see the migration for
 * why a highlight deliberately holds no price, image or copy of its own.
 */
class ProductHighlight extends Model
{
    protected $table = 'product_highlights';

    protected $fillable = [
        'website_id',
        'highlight_name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(ProductHighlightDetail::class, 'product_highlight_id')->orderBy('id');
    }

    /**
     * The products themselves, through the detail rows.
     *
     * Alongside details() rather than instead of it: the admin screen edits
     * lines and wants the rows, while everything that renders a highlight
     * wants the products.
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_highlight_details', 'product_highlight_id', 'product_id')
            ->orderBy('product_highlight_details.id');
    }

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Display order.
     *
     * By id, so highlights read in the order they were created -- the same
     * choice package_details makes. The scope exists even though it is one
     * clause: every list goes through it, so an `order` column later is a
     * change here and nowhere else.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('id');
    }
}
