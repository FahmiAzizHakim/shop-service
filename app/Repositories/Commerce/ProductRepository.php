<?php

namespace App\Repositories\Commerce;

use App\Models\Product;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Products, and the categories they are filed under.
 *
 * A product carries no website_id of its own: it belongs to a service, and the
 * service belongs to a website. That indirection is the whole reason
 * scopeWebsite() is overridden here -- every other read and write in the base
 * class then scopes correctly without knowing about it.
 *
 * The rows hanging off a product (images, variants, specifications) each have
 * their own repository; this one owns the product row and its category pivot.
 */
class ProductRepository extends BaseRepository
{
    protected $model = Product::class;

    /** What a product card needs, whether an admin or a visitor is reading. */
    protected const RELATIONS = ['service', 'categories', 'images', 'variants', 'specifications'];

    protected function scopeWebsite(Builder $query, $websiteId): Builder
    {
        return $query->when(
            $websiteId,
            fn ($q) => $q->whereHas('service', fn ($s) => $s->where('website_id', $websiteId))
        );
    }

    public function listForWebsite($websiteId = null)
    {
        return $this->forWebsite($websiteId)
            ->with(self::RELATIONS)
            ->withCount('views')
            ->orderByDesc('id')
            ->get();
    }

    public function findForWebsite($id, $websiteId = null): ?Model
    {
        return $this->forWebsite($websiteId)
            ->with(self::RELATIONS)
            ->withCount('views')
            ->where('id', $id)
            ->first();
    }

    /**
     * The public catalogue's products: active, under an active service, with
     * only the active images and variants loaded.
     *
     * "Active" is decided in this one query rather than at each endpoint, so a
     * product cannot be visible on one page and hidden on another.
     */
    public function activeForWebsite($websiteId, $serviceId = null)
    {
        return $this->query()
            ->whereHas('service', fn ($q) => $q->where('website_id', $websiteId)->where('is_active', true))
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->where('is_active', true)
            ->with([
                'images'   => fn ($q) => $q->where('is_active', true),
                'variants' => fn ($q) => $q->where('is_active', true),
                'specifications',
            ])
            ->withCount('views')
            ->orderBy('id')
            ->get();
    }

    /**
     * Active products by id with their live variants, keyed for pricing a
     * basket without a query per line.
     */
    public function activeByIds($websiteId, array $ids)
    {
        return $this->forWebsite($websiteId)
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->keyBy('id');
    }

    /** Whether a product is one this website is allowed to reference. */
    public function belongsToWebsite($productId, $websiteId): bool
    {
        return $this->forWebsite($websiteId)->where('id', $productId)->exists();
    }

    /**
     * Whether this product is one a visitor to this site can see.
     *
     * Stricter than belongsToWebsite(): "active" here means the same thing it
     * means in activeForWebsite() -- the product active *and* its service
     * active -- so the public endpoints cannot disagree about what is
     * visible. Existence only, because the caller is checking a claim rather
     * than reading a product.
     */
    public function activeExistsForWebsite($productId, $websiteId): bool
    {
        return $this->query()
            ->where('id', $productId)
            ->where('is_active', true)
            ->whereHas('service', fn ($q) => $q->where('website_id', $websiteId)->where('is_active', true))
            ->exists();
    }

    /**
     * Every product of one website with its view counts, for the stats table.
     *
     * One statement for the whole list: withCount() attaches each total as a
     * subquery, so a hundred products cost the same number of queries as one.
     * $since gives the second column -- the same count over a window, which is
     * what says whether a product is still being looked at or was.
     *
     * The service is eager-loaded because the table groups by it: products
     * belong to a website only through a service, so that is the column an
     * admin reads them under.
     */
    public function statsForWebsite($websiteId, Carbon $since)
    {
        return $this->forWebsite($websiteId)
            ->with('service')
            ->withCount([
                'views',
                'views as views_recent_count' => fn ($q) => $q->where('viewed_at', '>=', $since),
            ])
            ->withMax('views', 'viewed_at')
            ->orderByDesc('views_count')
            ->get();
    }

    public function syncCategories(Product $product, array $categoryIds): void
    {
        $product->categories()->sync($categoryIds);
    }

    public function detachCategories(Product $product): void
    {
        $product->categories()->detach();
    }
}
