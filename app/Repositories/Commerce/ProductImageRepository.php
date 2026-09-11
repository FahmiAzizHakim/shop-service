<?php

namespace App\Repositories\Commerce;

use App\Models\ProductImage;
use App\Repositories\BaseRepository;

class ProductImageRepository extends BaseRepository
{
    protected $model = ProductImage::class;

    public function forProduct($productId)
    {
        return $this->query()->where('product_id', $productId)->get();
    }

    /** The named images of one product -- scoped so an id from another product does nothing. */
    public function forProductByIds($productId, array $ids)
    {
        return $this->query()->where('product_id', $productId)->whereIn('id', $ids)->get();
    }

    public function deleteForProductByIds($productId, array $ids): void
    {
        $this->query()->where('product_id', $productId)->whereIn('id', $ids)->delete();
    }

    /** Where the next appended image sits in the gallery order. */
    public function nextOrder($productId): int
    {
        return (int) $this->query()->where('product_id', $productId)->max('order') + 1;
    }

    public function deleteForProduct($productId): void
    {
        $this->query()->where('product_id', $productId)->delete();
    }
}
