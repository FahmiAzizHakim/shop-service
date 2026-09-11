<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\ProductRepository;
use App\Repositories\Commerce\ProductViewRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * How often a product is looked at, and when.
 *
 * The counterpart to website-service's ContentViewService, and the same shape
 * on purpose: an admin comparing which articles and which products are read
 * should not have to learn two different answers.
 *
 * A view is recorded from a public endpoint, so recording one is on the path
 * of something a visitor is waiting for. That shapes both halves of this
 * class: record() must never be the reason a page fails, and the stats must
 * never be counted on the way in -- they are counted when an admin asks for
 * them, off an indexed column, and cost the visitor nothing.
 */
class ProductViewService
{
    /** How many days of history the series covers when no window is asked for. */
    const DEFAULT_DAYS = 30;

    protected $views;
    protected $products;

    public function __construct(ProductViewRepository $views, ProductRepository $products)
    {
        $this->views    = $views;
        $this->products = $products;
    }

    /**
     * Log one opening of a product page.
     *
     * Swallows its own failure on purpose. This is a counter behind a public
     * page: if the insert fails -- the table not migrated yet, the connection
     * gone -- the visitor should still get their page. The failure is logged,
     * because a counter that has silently stopped counting is worse than one
     * that is visibly broken.
     *
     * Every call counts, including a reload. Telling a repeat visit from a new
     * one means keeping something about the visitor, and this table
     * deliberately keeps nothing -- so a "view" here means an opening of the
     * page, not a person, and the stats are labelled that way.
     */
    public function record($productId): void
    {
        try {
            $this->views->create([
                'product_id' => $productId,
                'viewed_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("product view not recorded for product {$productId}: {$e->getMessage()}");
        }
    }

    /** How many times this product has been opened, all time. */
    public function countFor($productId): int
    {
        return $this->views->countForProduct($productId);
    }

    /**
     * The stats table: every product of one website, most-viewed first.
     *
     * @return array{totals: array, products: array, daily: array, days: int}
     */
    public function overview($websiteId, int $days = self::DEFAULT_DAYS): array
    {
        $days  = $this->clampDays($days);
        $since = $this->since($days);

        $products = $this->products->statsForWebsite($websiteId, $since);

        return [
            'days'   => $days,
            'totals' => [
                'products'     => $products->count(),
                'views'        => (int) $products->sum('views_count'),
                'views_recent' => (int) $products->sum('views_recent_count'),
            ],
            'products' => $products->map(fn ($product) => [
                'id'             => (int) $product->id,
                'name'           => $product->products_name,
                'code'           => $product->products_code,
                'service_id'     => (int) $product->service_id,
                'service_name'   => optional($product->service)->service_name,
                'is_active'      => (bool) $product->is_active,
                'views'          => (int) $product->views_count,
                'views_recent'   => (int) $product->views_recent_count,
                'last_viewed_at' => $this->iso($product->views_max_viewed_at),
            ])->values()->all(),
            'daily' => $this->fillDays($this->views->dailyForWebsite($websiteId, $since), $days),
        ];
    }

    /**
     * One product's own numbers, with the day-by-day series behind them.
     *
     * @return array{views: array, daily: array, days: int}
     */
    public function statsFor($product, int $days = self::DEFAULT_DAYS): array
    {
        $days = $this->clampDays($days);
        $id   = $product->id;

        return [
            'days'  => $days,
            'views' => [
                'total'           => $this->views->countForProduct($id),
                'today'           => $this->views->countForProductSince($id, Carbon::today()),
                'last_7_days'     => $this->views->countForProductSince($id, $this->since(7)),
                'last_30_days'    => $this->views->countForProductSince($id, $this->since(30)),
                'first_viewed_at' => $this->iso($this->views->firstViewedAt($id)),
                'last_viewed_at'  => $this->iso($this->views->lastViewedAt($id)),
            ],
            'daily' => $this->fillDays($this->views->dailyForProduct($id, $this->since($days)), $days),
        ];
    }

    /**
     * The start of the window, $days back and at midnight.
     *
     * Midnight rather than "this time $days ago" because the series is by
     * calendar day: a window that starts mid-afternoon makes its first day a
     * half day, which reads on a chart as a drop that never happened.
     */
    protected function since(int $days): Carbon
    {
        return Carbon::today()->subDays($days - 1);
    }

    /**
     * The series with its gaps filled, oldest first.
     *
     * A day nobody opened the product returns no row, and a chart drawn from
     * that would join Monday to Friday as though the week were three days
     * long. The zeroes are put back here, where the window is known.
     *
     * @param  array<string, int>  $counted
     * @return array<int, array{date: string, views: int}>
     */
    protected function fillDays(array $counted, int $days): array
    {
        $series = [];

        for ($day = $days - 1; $day >= 0; $day--) {
            $date = Carbon::today()->subDays($day)->toDateString();

            $series[] = ['date' => $date, 'views' => $counted[$date] ?? 0];
        }

        return $series;
    }

    /** A window has to be a window: at least a day, at most a year. */
    protected function clampDays(int $days): int
    {
        return max(1, min($days, 365));
    }

    /** Aggregates come back as strings from the driver, not as dates. */
    protected function iso($value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
