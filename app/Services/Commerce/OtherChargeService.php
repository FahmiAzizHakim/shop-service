<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\OtherChargeRepository;
use App\Repositories\Commerce\PackageDetailRepository;
use Illuminate\Support\Facades\DB;

class OtherChargeService
{
    protected $charges;
    protected $packageDetails;

    public function __construct(
        OtherChargeRepository $charges,
        PackageDetailRepository $packageDetails
    ) {
        $this->charges        = $charges;
        $this->packageDetails = $packageDetails;
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     * ========================= */
    public function getList($websiteId = null)
    {
        return $this->charges->listForWebsite($websiteId);
    }

    /* =========================
     * GET SINGLE ROW (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->charges->findForWebsite($id, $websiteId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create($params)
    {
        DB::beginTransaction();

        $sql = $this->charges->create($params);
        if (!$sql) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create other charge");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Other charge created successfully",
            "data"    => $sql,
        );
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id, $params)
    {
        DB::beginTransaction();

        $data = $this->charges->find($id);
        if (!$data) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Other charge not found");
        }

        $updated = $this->charges->update($data, $params);
        if (!$updated) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to update other charge");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Other charge updated successfully",
            "data"    => $data,
        );
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $data = $this->charges->find($id);

        if (!$data) {
            return array("status" => "failed", "message" => "Other charge not found");
        }

        DB::beginTransaction();
        // Drop package lines pointing at this charge so no package keeps a dangling row.
        $this->packageDetails->deleteForOtherCharge($id);
        $this->charges->delete($data);
        DB::commit();

        return array("status" => "success", "message" => "Other charge deleted successfully");
    }
}
