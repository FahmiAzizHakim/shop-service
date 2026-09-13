<?php

namespace App\Repositories\Commerce;

use App\Models\ProductReviewImage;
use App\Repositories\BaseRepository;

/**
 * The photos attached to reviews.
 *
 * The foreign key already cascades; these exist for the callers that have to
 * remove the files from disk as well, and so want the rows before they go.
 */
class ProductReviewImageRepository extends BaseRepository
{
    protected $model = ProductReviewImage::class;

    public function deleteForReview($reviewId): void
    {
        $this->query()->where('product_review_id', $reviewId)->delete();
    }

    public function deleteForReviewIds(array $reviewIds): void
    {
        if ($reviewIds === []) {
            return;
        }

        $this->query()->whereIn('product_review_id', $reviewIds)->delete();
    }
}
