<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\ServiceRepository;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    protected $services;

    public function __construct(ServiceRepository $services)
    {
        $this->services = $services;
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     * ========================= */
    public function getList($websiteId = null)
    {
        return $this->services->listForWebsite($websiteId);
    }

    /* =========================
     * GET SINGLE ROW (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->services->findForWebsite($id, $websiteId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create($params)
    {
        DB::beginTransaction();

        $sql = $this->services->create($params);
        if (!$sql) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create service");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Service created successfully",
            "data"    => $sql,
        );
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id, $params)
    {
        DB::beginTransaction();

        $data = $this->services->find($id);
        if (!$data) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Service not found");
        }

        $updated = $this->services->update($data, $params);
        if (!$updated) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to update service");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Service updated successfully",
            "data"    => $data,
        );
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $data = $this->services->find($id);

        if (!$data) {
            return array("status" => "failed", "message" => "Service not found");
        }

        $this->services->delete($data);
        return array("status" => "success", "message" => "Service deleted successfully");
    }
}
