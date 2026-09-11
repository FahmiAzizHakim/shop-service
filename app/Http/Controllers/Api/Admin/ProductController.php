<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Commerce\ProductRequest;
use App\Services\Helper\UploadService;
use App\Services\Commerce\ProductService;

/**
 * Products, with their categories, images, variants and specifications.
 *
 * A product is written whole: the nested collections are replaced from the
 * payload in one transaction rather than through per-row endpoints, so a
 * half-saved product cannot exist.
 *
 * Writes are multipart (images ride along), so update is a POST -- PHP does
 * not parse a multipart body on PUT.
 */
class ProductController extends ApiController
{
    public $service;
    public $upload;

    public function __construct(ProductService $service, UploadService $upload)
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
            return $this->notFound('Product');
        }

        return response()->json(['data' => $data]);
    }

    public function store(ProductRequest $request)
    {
        $params = $this->params($request);
        $params['created_by'] = acting_user_email();

        $result = $this->service->create(
            $params,
            $request->input('categories', []),
            $this->uploadImages($request),
            $request->input('variants', []),
            $request->input('specifications', [])
        );

        return $this->respond($result, 201);
    }

    public function update(ProductRequest $request, $id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Product');
        }

        $params = $this->params($request);
        $params['updated_by'] = acting_user_email();

        $result = $this->service->update(
            $id,
            $params,
            $request->input('categories', []),
            $this->uploadImages($request),
            $request->input('remove_images', []),
            $request->input('variants', []),
            $request->input('specifications', [])
        );

        return $this->respond($result);
    }

    public function destroy($id)
    {
        if (!$this->service->getRow($id, admin_website_id())) {
            return $this->notFound('Product');
        }

        return $this->respond($this->service->delete($id));
    }

    /**
     * Persistable product columns from the request.
     */
    private function params(ProductRequest $request): array
    {
        $data = $request->safe()->only([
            'service_id', 'products_name', 'products_code', 'products_price', 'products_description',
            'products_weight', 'products_width', 'products_length', 'products_height', 'remark', 'is_active',
        ]);

        // price column is NOT NULL (default 0.00)
        $data['products_price'] = $request->input('products_price') ?: 0;

        return $data;
    }

    /**
     * Upload any submitted images and return rows for product_images.
     */
    private function uploadImages(ProductRequest $request): array
    {
        $images = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $up = $this->upload->store($file, 'product');
                if ($up) {
                    $images[] = ['image_name' => $up['original'], 'image_url' => $up['uploaded']];
                }
            }
        }

        return $images;
    }
}
