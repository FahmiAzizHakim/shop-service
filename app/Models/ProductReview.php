<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a buyer said about one product they bought.
 *
 * Always attached to the order it came from -- see the migration for why that
 * is a column rather than a convention.
 */
class ProductReview extends Model
{
    protected $table = 'product_reviews';

    protected $fillable = [
        'website_id',
        'product_id',
        'transaction_id',
        'reviewer_name',
        'rating',
        'review_text',
        'is_published',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'is_published' => 'boolean',
    ];

    /**
     * The order statuses a review may be left against.
     *
     * "Verified purchase" means the money arrived, not merely that a receipt
     * exists: an order can be placed and never paid, or voided, and neither is
     * a purchase. So the window opens at Paid and stays open through the rest
     * of the order's life -- Confirmed, Delivery, Completed -- because a buyer
     * writes a review after the thing turns up, which is the far end of that
     * list.
     *
     * Deliberately excluded: Saved, Approval, Awaiting Payment and
     * Confirmation Payment (no money yet), Void and Rejected (no sale).
     *
     * Kept here rather than in the service because it is a fact about when a
     * review is allowed to exist, which is the model's business, and because
     * it is the one line to edit if the shop ever wants reviews earlier.
     */
    public const REVIEWABLE_STATUSES = [
        'STSPD', // Paid
        'STSCF', // Confirmed
        'STSDV', // Delivery
        'STSOK', // Completed
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function images()
    {
        return $this->hasMany(ProductReviewImage::class, 'product_review_id')->orderBy('id');
    }

    public function scopeForWebsite($query, $websiteId)
    {
        return $query->where('website_id', $websiteId);
    }

    /** What a visitor is allowed to see. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Display order: newest first.
     *
     * The opposite of every other list in this service, and on purpose -- a
     * review is news about a product, so the most recent one is the one worth
     * reading first. Catalogue rows are a set, and read in the order they were
     * built.
     */
    public function scopeOrdered($query)
    {
        return $query->orderByDesc('id');
    }
}
