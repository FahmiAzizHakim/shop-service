<?php

namespace App\Repositories\Commerce;

use App\Models\ProductHighlightDetail;
use App\Repositories\BaseRepository;

/**
 * The product lines inside a highlight.
 *
 * deleteForProduct() exists for the same reason PackageDetailRepository's
 * does: a highlight line is one of the places a deleted product would leave a
 * dangling reference, and ProductService::delete() clears both.
 */
class ProductHighlightDetailRepository extends BaseRepository
{
    protected $model = ProductHighlightDetail::class;

    /** The product ids already in a highlight. */
    public function productIdsFor($highlightId): array
    {
        return $this->query()
            ->where('product_highlight_id', $highlightId)
            ->pluck('product_id')
            ->all();
    }

    /** Drop the lines of a highlight that name products no longer in the set. */
    public function deleteForHighlightExcept($highlightId, array $keepProductIds): void
    {
        $this->query()
            ->where('product_highlight_id', $highlightId)
            ->when($keepProductIds, fn ($q) => $q->whereNotIn('product_id', $keepProductIds))
            ->delete();
    }

    public function deleteForProduct($productId): void
    {
        $this->query()->where('product_id', $productId)->delete();
    }
}
