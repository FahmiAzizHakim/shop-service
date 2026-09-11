<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\CategoryRequest;
use App\Services\Commerce\CategoryService;

/**
 * Product categories. Self-nesting: a category may sit under a parent, and
 * parents() lists the rows eligible to be one.
 */
class CategoryController extends ApiController
{
    public $service;

    public function __construct(CategoryService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        return $this->items($this->service->getList(admin_website_id()));
    }

    /**
     * Candidates for the parent picker. Pass ?exclude={id} when editing, so a
     * category is not offered as its own parent.
     */
    public function parents()
    {
        return $this->items(
            $this->service->getParents(admin_website_id(), request()->query('exclude'))
        );
    }

    public function show($id)
    {
        $data = $this->service->getRow($id, admin_website_id());

        if (!$data) {
            return $this->notFound('Category');
        }

        return response()->json(['data' => $data]);
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->safe()->only(['category_name', 'category_code', 'parent_id', 'remark', 'is_active']);
        $data['website_id'] = admin_website_id();
        $data['created_by'] = acting_user_email();

        return $this->respond($this->service->create($data), 201);
    }

    public function update(CategoryRequest $request, $id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Category');
        }

        $data = $request->safe()->only(['category_name', 'category_code', 'parent_id', 'remark', 'is_active']);
        $data['updated_by'] = acting_user_email();

        return $this->respond($this->service->update($id, $data));
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Category');
        }

        return $this->respond($this->service->delete($id));
    }
}
