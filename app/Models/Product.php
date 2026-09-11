<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'service_id',
        'products_name',
        'products_code',
        'products_price',
        'products_description',
        'products_weight',
        'products_width',
        'products_length',
        'products_height',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function categories()
    {
        // pivot product_categories has created_at only (no updated_at)
        return $this->belongsToMany(Category::class, 'product_categories', 'product_id', 'category_id');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function specifications()
    {
        return $this->hasMany(ProductSpecification::class, 'product_id')->orderBy('id');
    }

    /**
     * Every opening of this product's page. The stats are counts over this
     * relation -- withCount('views') rather than a counter column on
     * products, so a row is never written to by being read.
     */
    public function views()
    {
        return $this->hasMany(ProductView::class, 'product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
