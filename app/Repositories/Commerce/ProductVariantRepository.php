<?php

namespace App\Repositories\Commerce;

use App\Models\ProductVariant;
use App\Repositories\BaseRepository;

class ProductVariantRepository extends BaseRepository
{
    protected $model = ProductVariant::class;

    /** One variant of one product; scoped so a foreign id resolves to nothing. */
    public function findForProduct($productId, $id): ?ProductVariant
    {
        return $this->query()->where('product_id', $productId)->where('id', $id)->first();
    }

    public function deleteForProduct($productId): void
    {
        $this->query()->where('product_id', $productId)->delete();
    }
}
