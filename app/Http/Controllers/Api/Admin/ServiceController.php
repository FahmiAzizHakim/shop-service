<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\ServiceRequest;
use App\Services\Helper\UploadService;
use App\Services\Commerce\ServiceService;

/**
 * Services: the top level of the catalogue, and what products and packages are
 * grouped under.
 *
 * Writes carry an image and an icon, so update is a POST rather than a PUT:
 * PHP does not parse a multipart body on PUT and the files would arrive empty.
 */
class ServiceController extends ApiController
{
    public $service;
    public $upload;

    public function __construct(ServiceService $service, UploadService $upload)
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
            return $this->notFound('Service');
        }

        return response()->json(['data' => $data]);
    }

    public function store(ServiceRequest $request)
    {
        $data = $this->params($request);
        $data['website_id'] = admin_website_id();
        $data['created_by'] = acting_user_email();

        return $this->respond($this->service->create($data), 201);
    }

    public function update(ServiceRequest $request, $id)
    {
        // Ensure the service belongs to the caller's website.
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Service');
        }

        $data = $this->params($request);
        $data['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Service');
        }

        return $this->respond($this->service->delete($id));
    }

    /**
     * Persistable columns, plus the image if one was re-uploaded. An image left
     * out keeps the one already stored.
     *
     * service_icon is a column like any other here, not a file: it holds a
     * Bootstrap Icons class name, which is what the storefront renders it as.
     */
    private function params(ServiceRequest $request): array
    {
        $data = $request->safe()->only([
            'service_name', 'service_title', 'service_subtitle', 'service_description',
            'service_icon', 'remark', 'is_active',
        ]);

        if ($request->hasFile('service_image')) {
            $up = $this->upload->store($request->file('service_image'), 'service');
            if ($up) {
                $data['service_image'] = $up['uploaded'];
            }
        }

        return $data;
    }
}
