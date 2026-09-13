<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One photo attached to a review.
 */
class ProductReviewImage extends Model
{
    protected $table = 'product_review_images';

    // Table only has created_at (no updated_at), as product_images.
    public $timestamps = false;

    protected $fillable = [
        'product_review_id',
        'image_name',
        'image_url',
    ];

    public function review()
    {
        return $this->belongsTo(ProductReview::class, 'product_review_id');
    }
}
