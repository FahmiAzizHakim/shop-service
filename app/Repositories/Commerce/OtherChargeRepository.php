<?php

namespace App\Repositories\Commerce;

use App\Models\OtherCharge;
use App\Repositories\BaseRepository;

class OtherChargeRepository extends BaseRepository
{
    protected $model = OtherCharge::class;

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)->orderBy('id')->get();
    }

    /**
     * One live charge of a website, by its business code.
     *
     * Active only, and matched on the website exactly rather than through
     * forWebsite(): a null id there means "every website", which for a lookup
     * that decides what a customer is billed would let one site's fee be
     * charged on another's order. Checkout uses this to price the QRIS admin
     * fee -- see config/checkout.php.
     */
    public function activeByCodeForWebsite(string $code, $websiteId): ?OtherCharge
    {
        return $this->query()
            ->where('website_id', $websiteId)
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }
}
