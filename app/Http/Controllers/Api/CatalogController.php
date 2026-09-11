<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PackageResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Services\Catalog\CatalogDataService;
use App\Services\Commerce\ProductViewService;
use Illuminate\Http\Request;

/**
 * The public catalogue of one site: services, products and packages.
 *
 * Scoped by website id in the path rather than by token -- this is what a
 * visitor's browser reads, so no auth. Every endpoint accepts ?service_id= so
 * a frontend can drive the service tabs without filtering client-side.
 */
class CatalogController extends ApiController
{
    protected $data;
    protected $views;

    public function __construct(CatalogDataService $data, ProductViewService $views)
    {
        $this->data  = $data;
        $this->views = $views;
    }

    /**
     * Everything the catalogue section renders, in one request: the services,
     * their products and the packages, plus the has_packages flag that
     * website-service wants back to decide its layout.
     */
    public function index(Request $request, $website)
    {
        $websiteId = (int) $website;
        $serviceId = $request->query('service_id');
        $serviceId = $serviceId ? (int) $serviceId : null;

        return response()->json([
            'data' => [
                'has_packages' => $this->data->hasPackages($websiteId),
                'services'     => ServiceResource::collection($this->data->services($websiteId))->resolve(),
                'products'     => ProductResource::collection($this->data->products($websiteId, $serviceId))->resolve(),
                'packages'     => PackageResource::collection($this->data->packages($websiteId, $serviceId))->resolve(),
            ],
        ]);
    }

    /**
     * Services, optionally with their products nested (?with=products), which
     * is the shape the catalogue section renders.
     */
    public function services(Request $request, $website)
    {
        $websiteId = (int) $website;
        $services  = $this->data->services($websiteId);

        if ($request->query('with') === 'products') {
            $products = $this->data->products($websiteId)->groupBy('service_id');

            $services->each(fn ($s) => $s->setRelation('products', $products->get($s->id, collect())));
        }

        return ServiceResource::collection($services);
    }

    public function products(Request $request, $website)
    {
        $serviceId = $request->query('service_id');

        return ProductResource::collection(
            $this->data->products((int) $website, $serviceId ? (int) $serviceId : null)
        );
    }

    public function product($website, $id)
    {
        $product = $this->data->products((int) $website)->firstWhere('id', (int) $id);

        if (!$product) {
            return $this->notFound('Product');
        }

        return new ProductResource($product);
    }

    /**
     * POST .../products/{id}/view -- count one opening of a product page.
     *
     * A write, and a public one, because the visitor who triggers it has no
     * token. It exists as its own endpoint rather than being folded into the
     * GET above because of how the storefront actually renders: the product
     * page reads its product out of the catalogue payload the site store
     * already holds (see vue-vite's ProductView.vue, which says why -- a
     * spinner over data the page has would be a regression), so the detail
     * GET is not what happens when a product is looked at. Counting there
     * would count API reads and miss nearly every real view.
     *
     * The id is checked against what this site publishes before anything is
     * written: an unknown or hidden product is a 404, not a counted view, so
     * one site's numbers cannot be moved by naming another site's product.
     *
     * Answers with the new total, which is the one thing a caller might want
     * back -- and what makes the endpoint worth calling from anywhere else.
     */
    public function view($website, $id)
    {
        $websiteId = (int) $website;
        $productId = (int) $id;

        if (!$this->data->productIsVisible($websiteId, $productId)) {
            return $this->notFound('Product');
        }

        $this->views->record($productId);

        return response()->json([
            'data' => ['views' => $this->views->countFor($productId)],
        ]);
    }

    public function packages(Request $request, $website)
    {
        $serviceId = $request->query('service_id');

        return PackageResource::collection(
            $this->data->packages((int) $website, $serviceId ? (int) $serviceId : null)
        );
    }
}
