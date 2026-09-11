<?php

namespace App\Repositories\Commerce;

use App\Models\PackageDetail;
use App\Repositories\BaseRepository;

/**
 * The lines inside a package: a product, a charge or a plain benefit.
 *
 * The deleteFor* methods exist because a package line is the one place a
 * deleted product or charge would leave a dangling reference.
 */
class PackageDetailRepository extends BaseRepository
{
    protected $model = PackageDetail::class;

    public function findForPackage($packageId, $id): ?PackageDetail
    {
        return $this->query()->where('package_id', $packageId)->where('id', $id)->first();
    }

    public function deleteForProduct($productId): void
    {
        $this->query()->where('product_id', $productId)->delete();
    }

    public function deleteForOtherCharge($otherChargeId): void
    {
        $this->query()->where('other_charge_id', $otherChargeId)->delete();
    }
}
