<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\OtherChargeRequest;
use App\Services\Commerce\OtherChargeService;

/**
 * Named surcharges a package line can carry (installation, bracket, and so on).
 */
class OtherChargeController extends ApiController
{
    public $service;

    public function __construct(OtherChargeService $service)
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
            return $this->notFound('Other charge');
        }

        return response()->json(['data' => $data]);
    }

    public function store(OtherChargeRequest $request)
    {
        $data = $request->safe()->only(['code', 'name', 'description', 'remark', 'price', 'is_active']);
        $data['website_id'] = admin_website_id();
        $data['created_by'] = acting_user_email();

        return $this->respond($this->service->create($data), 201);
    }

    public function update(OtherChargeRequest $request, $id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Other charge');
        }

        $data = $request->safe()->only(['code', 'name', 'description', 'remark', 'price', 'is_active']);
        $data['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Other charge');
        }

        return $this->respond($this->service->delete($id));
    }
}
