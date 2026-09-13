<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\ProductHighlightRequest;
use App\Services\Commerce\ProductHighlightService;
use App\Services\Commerce\ProductService;

/**
 * Product highlights: a named set of products the storefront shows together.
 *
 * Written whole, as a package is -- the heading and the set of products go in
 * one transaction. Plain PUT on update rather than POST, because nothing here
 * carries a file: the products bring their own images.
 */
class ProductHighlightController extends ApiController
{
    public $service;
    public $products;

    public function __construct(ProductHighlightService $service, ProductService $products)
    {
        $this->service  = $service;
        $this->products = $products;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Highlight');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * What the product picker offers, scoped to the caller's website. Named
     * and shaped like PackageController::options() so the two screens can use
     * the same picker.
     */
    public function options()
    {
        return response()->json([
            'data' => [
                'products' => $this->products->getList(admin_website_id()),
            ],
        ]);
    }

    public function store(ProductHighlightRequest $request)
    {
        $params = $request->safe()->only(['highlight_name', 'is_active']);
        $params['website_id'] = admin_website_id();
        $params['created_by'] = acting_user_email();

        return $this->respond(
            $this->service->create($params, $request->input('product_ids', [])),
            201
        );
    }

    public function update(ProductHighlightRequest $request, $id)
    {
        // Ensure the highlight belongs to the caller's website.
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Highlight');
        }

        $params = $request->safe()->only(['highlight_name', 'is_active']);
        $params['updated_by'] = acting_user_email();

        return $this->respond(
            $this->service->update($id, $params, $request->input('product_ids', []))
        );
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Highlight');
        }

        return $this->respond($this->service->delete($id));
    }
}
