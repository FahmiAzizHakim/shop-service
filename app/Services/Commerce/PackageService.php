<?php

namespace App\Services\Commerce;

use App\Models\Package;
use App\Repositories\Commerce\OtherChargeRepository;
use App\Repositories\Commerce\PackageDetailRepository;
use App\Repositories\Commerce\PackageRepository;
use App\Repositories\Commerce\ProductRepository;
use Illuminate\Support\Facades\DB;

class PackageService
{
    protected $packages;
    protected $details;
    protected $products;
    protected $charges;

    public function __construct(
        PackageRepository $packages,
        PackageDetailRepository $details,
        ProductRepository $products,
        OtherChargeRepository $charges
    ) {
        $this->packages = $packages;
        $this->details  = $details;
        $this->products = $products;
        $this->charges  = $charges;
    }

    /* =========================
     * GET LIST (scoped to website when provided)
     *
     * $serviceId null means "no service filter", NOT "packages without a
     * service" -- for those, use Package::forService(null) or the '' key of
     * getListByService().
     * ========================= */
    public function getList($websiteId = null, $serviceId = null)
    {
        return $this->packages->listForWebsite($websiteId, $serviceId);
    }

    /* =========================
     * GET LIST grouped by service, for "packages of this service" listings.
     * Packages tied to no service are keyed under ''.
     * ========================= */
    public function getListByService($websiteId = null)
    {
        return $this->getList($websiteId)->groupBy(fn ($p) => (string) $p->service_id);
    }

    /* =========================
     * GET SINGLE ROW (scoped to website when provided)
     * ========================= */
    public function getRow($id, $websiteId = null)
    {
        return $this->packages->findForWebsite($id, $websiteId);
    }

    /* =========================
     * CREATE
     * ========================= */
    public function create($params, array $details = [])
    {
        DB::beginTransaction();

        $package = $this->packages->create($params);
        if (!$package) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Failed to create package");
        }

        $this->syncDetails($package, $details);

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Package created successfully",
            "data"    => $package,
        );
    }

    /* =========================
     * UPDATE
     * ========================= */
    public function update($id, $params, array $details = [])
    {
        DB::beginTransaction();

        $package = $this->packages->find($id);
        if (!$package) {
            DB::rollBack();
            return array("status" => "failed", "message" => "Package not found");
        }

        $this->packages->update($package, $params);
        $this->syncDetails($package, $details);

        DB::commit();
        return array(
            "status"  => "success",
            "message" => "Package updated successfully",
            "data"    => $package,
        );
    }

    /* =========================
     * DELETE
     * ========================= */
    public function delete($id)
    {
        $package = $this->packages->find($id);

        if (!$package) {
            return array("status" => "failed", "message" => "Package not found");
        }

        DB::beginTransaction();
        $this->packages->deleteDetails($package);
        $this->packages->delete($package);
        DB::commit();

        return array("status" => "success", "message" => "Package deleted successfully");
    }

    /**
     * Create / update / delete detail lines from submitted rows.
     * Each row: [id?, _remove?, line_type, product_id, other_charge_id, other_benefit, qty].
     *
     * A line holds exactly one of the three kinds; the other two columns are
     * nulled out so switching a line's type never leaves a stale reference.
     */
    private function syncDetails(Package $package, array $details): void
    {
        foreach ($details as $row) {
            $id     = $row['id'] ?? null;
            $remove = !empty($row['_remove']);
            $data   = $this->detailData($package->website_id, $row);

            if ($id) {
                $detail = $this->details->findForPackage($package->id, $id);
                if (!$detail) {
                    continue;
                }
                if ($remove || $data === null) {
                    $this->details->delete($detail);
                    continue;
                }
                $this->details->update($detail, $data);
            } else {
                // Skip blank / removed new rows.
                if ($remove || $data === null) {
                    continue;
                }
                $data['package_id'] = $package->id;
                $this->details->create($data);
            }
        }
    }

    /**
     * Normalise one submitted line into persistable columns, or null when the
     * line is empty / references something outside this website.
     */
    private function detailData($websiteId, array $row): ?array
    {
        $type = $row['line_type'] ?? '';
        $qty  = max(1, (int) ($row['qty'] ?? 1));

        if ($type === 'product') {
            $productId = $row['product_id'] ?? null;
            // Products carry no website_id of their own; ownership runs
            // through the service, which the repository knows how to follow.
            if (!$productId || !$this->products->belongsToWebsite($productId, $websiteId)) {
                return null;
            }

            return ['product_id' => $productId, 'other_charge_id' => null, 'other_benefit' => null, 'qty' => $qty];
        }

        if ($type === 'charge') {
            $chargeId = $row['other_charge_id'] ?? null;
            if (!$chargeId || !$this->charges->existsForWebsite($chargeId, $websiteId)) {
                return null;
            }

            return ['product_id' => null, 'other_charge_id' => $chargeId, 'other_benefit' => null, 'qty' => $qty];
        }

        if ($type === 'benefit') {
            $benefit = trim($row['other_benefit'] ?? '');
            if ($benefit === '') {
                return null;
            }

            return ['product_id' => null, 'other_charge_id' => null, 'other_benefit' => $benefit, 'qty' => $qty];
        }

        return null;
    }
}
