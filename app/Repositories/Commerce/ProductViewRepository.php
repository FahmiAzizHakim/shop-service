<?php

namespace App\Repositories\Commerce;

use App\Models\Product;
use App\Models\ProductView;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The view log: written when a product page opens, read by the stats.
 *
 * Counts per product are not here -- they come off the products query as
 * withCount(), which answers a list in one statement instead of one per row.
 * See ProductRepository::statsForWebsite(). What is here is what only this
 * table can answer: one product's totals, and the day-by-day series.
 */
class ProductViewRepository extends BaseRepository
{
    protected $model = ProductView::class;

    /**
     * product_views has no website_id, and neither has products: a view
     * belongs to a website through its product, and the product through its
     * service. Both hops are made here, so overriding this one method leaves
     * findForWebsite() and the rest of BaseRepository correct for this table.
     */
    protected function scopeWebsite(Builder $query, $websiteId): Builder
    {
        return $query->when($websiteId, fn ($q) => $q->whereIn(
            'product_id',
            Product::query()
                ->select('id')
                ->whereHas('service', fn ($s) => $s->where('website_id', $websiteId))
        ));
    }

    public function countForProduct($productId): int
    {
        return $this->query()->where('product_id', $productId)->count();
    }

    /** Views of one product since a moment -- "today", "last 7 days". */
    public function countForProductSince($productId, Carbon $since): int
    {
        return $this->query()
            ->where('product_id', $productId)
            ->where('viewed_at', '>=', $since)
            ->count();
    }

    public function firstViewedAt($productId)
    {
        return $this->query()->where('product_id', $productId)->min('viewed_at');
    }

    public function lastViewedAt($productId)
    {
        return $this->query()->where('product_id', $productId)->max('viewed_at');
    }

    /**
     * Views per calendar day for one product, oldest first.
     *
     * Only days that had a view come back; the days in between are filled in
     * by the Service, because a chart needs the zeroes and a query that
     * invents rows for them is a query nobody can read.
     */
    public function dailyForProduct($productId, Carbon $since): array
    {
        return $this->dailySeries(
            $this->query()->where('product_id', $productId),
            $since
        );
    }

    /** The same series across every product of one website. */
    public function dailyForWebsite($websiteId, Carbon $since): array
    {
        return $this->dailySeries($this->forWebsite($websiteId), $since);
    }

    public function countForWebsite($websiteId): int
    {
        return $this->forWebsite($websiteId)->count();
    }

    protected function dailySeries(Builder $query, Carbon $since): array
    {
        return $query->where('viewed_at', '>=', $since)
            ->selectRaw('DATE(viewed_at) as view_date, COUNT(*) as total')
            ->groupBy('view_date')
            ->orderBy('view_date')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->view_date => (int) $row->total])
            ->all();
    }
}
