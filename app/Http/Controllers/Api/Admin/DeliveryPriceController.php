<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\DeliveryPriceRequest;
use App\Services\Commerce\DeliveryPriceService;

/**
 * The delivery fare table: a price per area, at whichever level was set --
 * subdistrict, district or city.
 *
 * Checkout resolves a fare through the same service, narrowest level first.
 * The region lists the editor needs are public (RegionController), because
 * the checkout address form reads them too.
 */
class DeliveryPriceController extends ApiController
{
    public $service;

    public function __construct(DeliveryPriceService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Delivery price');
        }

        return response()->json([
            'data' => $data,
            // The city's province, so an editor can preselect the cascade.
            'meta' => ['province_code' => $this->service->provinceOfCity($data->city_code)],
        ]);
    }

    public function store(DeliveryPriceRequest $request)
    {
        $params = $this->params($request);
        $params['website_id'] = admin_website_id();
        $params['created_by'] = acting_user_email();

        return $this->respond($this->service->create($params), 201);
    }

    public function update(DeliveryPriceRequest $request, $id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Delivery price');
        }

        $params = $this->params($request);
        $params['website_id'] = admin_website_id();
        $params['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $params));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Delivery price');
        }

        return $this->respond($this->service->delete($id));
    }

    private function params(DeliveryPriceRequest $request): array
    {
        return $request->safe()->only([
            'city_code', 'district_code', 'subdistrict_code', 'price', 'is_active',
        ]);
    }
}
