<?php

namespace App\Services\Commerce;

use App\Repositories\Commerce\BankRepository;
use Illuminate\Support\Facades\DB;

class BankService
{
    protected $banks;

    public function __construct(BankRepository $banks)
    {
        $this->banks = $banks;
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     * ========================= */
    public function getList($websiteId = null)
    {
        return $this->banks->listForWebsite($websiteId);
    }

    /* =========================
     * GET LIST for the public receipt: active accounts only
     * ========================= */
    public function getPublicList($websiteId)
    {
        return $this->banks->activeForWebsite($websiteId);
    }

    /* =========================
     * GET SINGLE ROW (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->banks->findForWebsite($id, $websiteId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create($params)
    {
        DB::beginTransaction();

        $sql = $this->banks->create($params);
        if (!$sql) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create bank");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Bank created successfully",
            "data"    => $sql,
        );
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id, $params)
    {
        DB::beginTransaction();

        $data = $this->banks->find($id);
        if (!$data) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Bank not found");
        }

        $updated = $this->banks->update($data, $params);
        if (!$updated) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to update bank");
        }

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Bank updated successfully",
            "data"    => $data,
        );
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $data = $this->banks->find($id);

        if (!$data) {
            return array("status" => "failed", "message" => "Bank not found");
        }

        $this->banks->delete($data);
        return array("status" => "success", "message" => "Bank deleted successfully");
    }
}
