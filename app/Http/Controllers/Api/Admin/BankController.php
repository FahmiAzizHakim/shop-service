<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\BankRequest;
use App\Services\Helper\UploadService;
use App\Services\Commerce\BankService;

/**
 * The accounts a buyer can transfer to. Listed on the public receipt, so the
 * rows here are what a customer is asked to pay into.
 *
 * Writes carry a logo, so update is a POST rather than a PUT.
 */
class BankController extends ApiController
{
    public $service;
    public $upload;

    public function __construct(BankService $service, UploadService $upload)
    {
        $this->service = $service;
        $this->upload  = $upload;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Bank');
        }

        return response()->json(['data' => $data]);
    }

    public function store(BankRequest $request)
    {
        $data = $this->params($request);
        $data['website_id'] = admin_website_id();
        $data['created_by'] = acting_user_email();

        return $this->respond($this->service->create($data), 201);
    }

    public function update(BankRequest $request, $id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Bank');
        }

        $data = $this->params($request);
        $data['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Bank');
        }

        return $this->respond($this->service->delete($id));
    }

    /**
     * Persistable columns, plus the logo when one was uploaded. Leaving the
     * file out keeps the logo already stored.
     */
    private function params(BankRequest $request): array
    {
        $data = $request->safe()->only([
            'bank_name', 'bank_account', 'account_name', 'branch', 'remark', 'is_active',
        ]);

        if ($request->hasFile('logo')) {
            $up = $this->upload->store($request->file('logo'), 'bank');
            if ($up) {
                $data['logo'] = $up['uploaded'];
            }
        }

        return $data;
    }
}
