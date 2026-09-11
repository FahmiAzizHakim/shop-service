<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\PackageRequest;
use App\Services\Commerce\OtherChargeService;
use App\Services\Commerce\PackageService;
use App\Services\Commerce\ProductService;
use App\Services\Commerce\ServiceService;

/**
 * Packages: a priced bundle of products, charges and plain perks.
 *
 * A package is written whole -- its detail lines are replaced from the payload
 * in one transaction, as products are.
 */
class PackageController extends ApiController
{
    public $service;
    public $products;
    public $charges;
    public $services;

    public function __construct(
        PackageService $service,
        ProductService $products,
        OtherChargeService $charges,
        ServiceService $services
    ) {
        $this->service  = $service;
        $this->products = $products;
        $this->charges  = $charges;
        $this->services = $services;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Package');
        }

        return response()->json(['data' => $data]);
    }

    /**
     * What a detail line may point at, scoped to the caller's website. One
     * call so an editor can populate all three pickers at once.
     */
    public function options()
    {
        $websiteId = admin_website_id();

        return response()->json([
            'data' => [
                'products' => $this->products->getList($websiteId),
                'charges'  => $this->charges->getList($websiteId),
                'services' => $this->services->getList($websiteId),
            ],
        ]);
    }

    public function store(PackageRequest $request)
    {
        $params = $this->params($request);
        $params['website_id'] = admin_website_id();
        $params['created_by'] = acting_user_email();

        return $this->respond(
            $this->service->create($params, $request->input('details', [])),
            201
        );
    }

    public function update(PackageRequest $request, $id)
    {
        // Ensure the package belongs to the caller's website.
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Package');
        }

        $params = $this->params($request);
        $params['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $params, $request->input('details', [])));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Package');
        }

        return $this->respond($this->service->delete($id));
    }

    /**
     * Persistable package columns from the request.
     */
    private function params(PackageRequest $request): array
    {
        $data = $request->safe()->only([
            'service_id', 'package_name', 'package_code', 'package_price', 'package_discount',
            'package_description', 'remark', 'is_active',
        ]);

        // safe()->only() drops a null service_id, so set it explicitly --
        // otherwise clearing it on edit would leave the old service.
        $data['service_id'] = $request->input('service_id') ?: null;

        // Both money columns are NOT NULL (default 0.00).
        $data['package_price']    = $request->input('package_price') ?: 0;
        $data['package_discount'] = $request->input('package_discount') ?: 0;

        return $data;
    }
}
