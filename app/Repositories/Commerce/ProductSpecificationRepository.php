<?php

namespace App\Repositories\Commerce;

use App\Models\ProductSpecification;
use App\Repositories\BaseRepository;

class ProductSpecificationRepository extends BaseRepository
{
    protected $model = ProductSpecification::class;

    /** One spec row of one product; scoped so a foreign id resolves to nothing. */
    public function findForProduct($productId, $id): ?ProductSpecification
    {
        return $this->query()->where('product_id', $productId)->where('id', $id)->first();
    }

    public function deleteForProduct($productId): void
    {
        $this->query()->where('product_id', $productId)->delete();
    }
}
