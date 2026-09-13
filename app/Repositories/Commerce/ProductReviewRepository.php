<?php

namespace App\Repositories\Commerce;

use App\Models\ProductReview;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;

class ProductReviewRepository extends BaseRepository
{
    protected $model = ProductReview::class;

    /**
     * What the moderation screen lists: every review of a website, published
     * or not, newest first. ?product_id= narrows it to one product.
     */
    public function listForWebsite($websiteId = null, $productId = null)
    {
        return $this->forWebsite($websiteId)
            ->with(['images', 'product'])
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->ordered()
            ->get();
    }

    public function findForWebsite($id, $websiteId = null): ?Model
    {
        return $this->forWebsite($websiteId)->with(['images', 'product'])->where('id', $id)->first();
    }

    /** What a visitor reads under a product: the published reviews, newest first. */
    public function publishedForProduct($websiteId, $productId)
    {
        return $this->forWebsite($websiteId)
            ->published()
            ->where('product_id', $productId)
            ->with('images')
            ->ordered()
            ->get();
    }

    /**
     * The reviews left from one receipt, whatever their state.
     *
     * Unpublished ones are included on purpose: this is what tells the receipt
     * page which products the buyer has already reviewed, and a review taken
     * down by a moderator must not read as "not reviewed yet" and invite the
     * buyer to write it again -- which the unique index would refuse anyway.
     */
    public function forTransaction($transactionId)
    {
        return $this->query()
            ->where('transaction_id', $transactionId)
            ->with('images')
            ->ordered()
            ->get();
    }

    /** Has this order already reviewed this product? */
    public function existsForTransactionProduct($transactionId, $productId): bool
    {
        return $this->query()
            ->where('transaction_id', $transactionId)
            ->where('product_id', $productId)
            ->exists();
    }

    /**
     * Average rating and count for a product, over published reviews only.
     *
     * One aggregate query rather than a sum in PHP, because the summary is
     * read by pages that do not want the reviews themselves.
     */
    public function summaryForProduct($websiteId, $productId): array
    {
        $row = $this->forWebsite($websiteId)
            ->published()
            ->where('product_id', $productId)
            ->selectRaw('COUNT(*) AS total, AVG(rating) AS average')
            ->first();

        $total = (int) ($row->total ?? 0);

        return [
            'count'   => $total,
            // Null rather than 0 when nothing has been rated: "no reviews yet"
            // and "rated zero" are different things, and a product cannot be
            // rated zero anyway -- the scale starts at one.
            'average' => $total ? round((float) $row->average, 2) : null,
        ];
    }

    /** Every review of a product, for the product's own delete path. */
    public function forProduct($productId)
    {
        return $this->query()->where('product_id', $productId)->with('images')->get();
    }
}
