<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\CategoryRepository;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    protected $categories;

    public function __construct(CategoryRepository $categories)
    {
        $this->categories = $categories;
    }

    public function getList($websiteId = null)
    {
        return $this->categories->listForWebsite($websiteId);
    }

    public function getRow($id, $websiteId = null)
    {
        return $this->categories->findForWebsite($id, $websiteId);
    }

    /**
     * Category options for the parent dropdown (same website, optional exclude).
     */
    public function getParents($websiteId = null, $excludeId = null)
    {
        return $this->categories->parentOptions($websiteId, $excludeId);
    }

    public function create($params)
    {
        DB::beginTransaction();

        $sql = $this->categories->create($params);
        if (!$sql) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create category");
        }

        DB::commit();
        return array("status" => "success", "message" => "Category created successfully", "data" => $sql);
    }

    public function update($id, $params)
    {
        DB::beginTransaction();

        $data = $this->categories->find($id);
        if (!$data) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Category not found");
        }

        $this->categories->update($data, $params);

        DB::commit();
        return array("status" => "success", "message" => "Category updated successfully", "data" => $data);
    }

    /**
     * A category is only removable once nothing depends on it: deleting one
     * that still has sub-categories or products would orphan them.
     */
    public function delete($id)
    {
        $data = $this->categories->find($id);

        if (!$data) {
            return array("status" => "failed", "message" => "Category not found");
        }

        if ($this->categories->countChildren($data) > 0) {
            return array("status" => "failed", "message" => "Cannot delete: this category has sub-categories.");
        }

        $usedByProducts = $this->categories->countProductsUsing($id);
        if ($usedByProducts > 0) {
            return array("status" => "failed", "message" => "Cannot delete: {$usedByProducts} product(s) use this category.");
        }

        $this->categories->delete($data);
        return array("status" => "success", "message" => "Category deleted successfully");
    }
}
