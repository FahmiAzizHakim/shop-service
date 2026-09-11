<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Services\Commerce\ProductService;
use App\Services\Commerce\ProductViewService;
use Illuminate\Http\Request;

/**
 * How often the catalogue's products are being looked at.
 *
 * Read-only, and separate from ProductController because it answers a
 * different question: that one is the editor -- create, edit, delete a
 * product -- and this one is the report. Nothing here writes.
 *
 * The same endpoints website-service exposes for articles
 * (Admin\ContentStatsController), so the two read alike: ?days=N sets the
 * window on both the "recent" column and the daily series, default 30 and
 * capped at a year by the Service. Counts are of page openings, not of
 * people -- product_views keeps nothing that could tell a returning visitor
 * from a new one, which is deliberate.
 */
class ProductStatsController extends ApiController
{
    protected $views;
    protected $products;

    public function __construct(ProductViewService $views, ProductService $products)
    {
        $this->views    = $views;
        $this->products = $products;
    }

    /**
     * GET /api/admin/products/stats -- every product, most-viewed first.
     */
    public function index(Request $request)
    {
        return $this->items(
            $this->views->overview(
                admin_website_id(),
                (int) $request->input('days', ProductViewService::DEFAULT_DAYS)
            )
        );
    }

    /**
     * GET /api/admin/products/{id}/stats -- one product's own numbers.
     */
    public function show(Request $request, $id)
    {
        $product = $this->products->getRow($id, admin_website_id());

        if (!$product) {
            return $this->notFound('Product');
        }

        $stats = $this->views->statsFor(
            $product,
            (int) $request->input('days', ProductViewService::DEFAULT_DAYS)
        );

        return $this->items([
            'product' => [
                'id'           => (int) $product->id,
                'name'         => $product->products_name,
                'code'         => $product->products_code,
                'service_id'   => (int) $product->service_id,
                'service_name' => optional($product->service)->service_name,
                'is_active'    => (bool) $product->is_active,
            ],
        ] + $stats);
    }
}
