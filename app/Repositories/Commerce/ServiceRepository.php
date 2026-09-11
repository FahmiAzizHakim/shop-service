<?php

namespace App\Repositories\Commerce;

use App\Models\Service;
use App\Repositories\BaseRepository;

/**
 * Services -- the top of the catalogue. A product hangs off one, which is also
 * how a product comes to belong to a website.
 */
class ServiceRepository extends BaseRepository
{
    protected $model = Service::class;

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)->orderBy('id')->get();
    }

    /** What a visitor may see: the public catalogue's slice of the same table. */
    public function activeForWebsite($websiteId)
    {
        return $this->forWebsite($websiteId)
            ->where('is_active', true)
            ->orderBy('service_name')
            ->get();
    }

    /** Active services by id, keyed for pricing a basket in one pass. */
    public function activeByIds($websiteId, array $ids)
    {
        return $this->forWebsite($websiteId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
    }
}
