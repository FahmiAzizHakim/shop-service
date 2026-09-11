<?php

namespace App\Services\Catalog;

use App\Repositories\Commerce\PackageRepository;
use App\Repositories\Commerce\ProductRepository;
use App\Repositories\Commerce\ServiceRepository;

/**
 * The public catalogue of one website: what a visitor is allowed to see.
 *
 * The admin services (ServiceService, ProductService, PackageService) read the
 * same tables unfiltered through the same repositories; this one is the read
 * model behind /api/v1, so it asks for the active* queries and "active" is
 * decided in one place instead of at every endpoint.
 *
 * website_id is a plain column: the websites themselves live in
 * website-service, and the two databases only agree on the ids.
 */
class CatalogDataService
{
    protected $services;
    protected $products;
    protected $packages;

    public function __construct(
        ServiceRepository $services,
        ProductRepository $products,
        PackageRepository $packages
    ) {
        $this->services = $services;
        $this->products = $products;
        $this->packages = $packages;
    }

    /**
     * Whether the site sells any package. A frontend passes the answer on to
     * website-service, which hides the packages block and re-points the hero
     * when there is none.
     */
    public function hasPackages($websiteId): bool
    {
        return $this->packages->anyActiveForWebsite($websiteId);
    }

    public function services($websiteId)
    {
        return $this->services->activeForWebsite($websiteId);
    }

    /**
     * Active products of a website, with everything a product card needs.
     * Optionally narrowed to one service.
     */
    public function products($websiteId, $serviceId = null)
    {
        return $this->products->activeForWebsite($websiteId, $serviceId);
    }

    /**
     * Whether a visitor to this site can see this product.
     *
     * For the endpoints that act on a product id rather than read one -- the
     * view counter is the only one so far. It asks the same question the
     * product list answers, so an id that is not on the site is not a thing
     * that can be counted against it.
     */
    public function productIsVisible($websiteId, $productId): bool
    {
        return $this->products->activeExistsForWebsite($productId, $websiteId);
    }

    /**
     * Active packages, cheapest first, with their contents and the product
     * images the cover falls back through.
     */
    public function packages($websiteId, $serviceId = null)
    {
        return $this->packages->activeForWebsite($websiteId, $serviceId);
    }
}
