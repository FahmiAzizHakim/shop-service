<?php

namespace App\Repositories\Commerce;

use App\Models\Package;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PackageRepository extends BaseRepository
{
    protected $model = Package::class;

    protected const RELATIONS = ['service', 'details.product', 'details.otherCharge'];

    /**
     * $serviceId null means "no service filter", NOT "packages without a
     * service" -- for those, pass null through forService() explicitly.
     */
    public function listForWebsite($websiteId = null, $serviceId = null)
    {
        return $this->forWebsite($websiteId)
            ->with(self::RELATIONS)
            ->when(!is_null($serviceId), fn ($q) => $q->forService($serviceId))
            ->orderByDesc('id')
            ->get();
    }

    public function findForWebsite($id, $websiteId = null): ?Model
    {
        return $this->forWebsite($websiteId)->with(self::RELATIONS)->where('id', $id)->first();
    }

    /**
     * The public catalogue's packages: cheapest first, with the contents and
     * the product images the cover falls back through.
     */
    public function activeForWebsite($websiteId, $serviceId = null)
    {
        return $this->forWebsite($websiteId)
            ->active()
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->with([
                'details.product.images' => fn ($q) => $q->where('is_active', true),
                'details.product.specifications',
                'details.otherCharge',
            ])
            ->orderBy('package_price')
            ->get();
    }

    /** Active packages by id with their contents, keyed for pricing a basket. */
    public function activeByIds($websiteId, array $ids)
    {
        return $this->forWebsite($websiteId)
            ->whereIn('id', $ids)
            ->active()
            ->with(['details.product', 'details.otherCharge'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Whether the site sells any package at all.
     *
     * The table check is here because this is asked of a service that may be
     * deployed before its migrations have run, and an unmigrated install
     * should read as "no packages" rather than as an error.
     */
    public function anyActiveForWebsite($websiteId): bool
    {
        return Schema::hasTable('packages')
            && $this->forWebsite($websiteId)->active()->exists();
    }

    public function deleteDetails(Package $package): void
    {
        $package->details()->delete();
    }
}
