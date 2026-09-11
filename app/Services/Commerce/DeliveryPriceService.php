<?php

namespace App\Services\Commerce;

use App\Models\DeliveryPrice;
use App\Repositories\Commerce\DeliveryPriceRepository;
use App\Repositories\Reference\RegionRepository;
use Illuminate\Support\Facades\DB;

class DeliveryPriceService
{
    protected $prices;
    protected $regions;

    public function __construct(DeliveryPriceRepository $prices, RegionRepository $regions)
    {
        $this->prices  = $prices;
        $this->regions = $regions;
    }

    /* =========================================================
     * CRUD
     * ========================================================= */

    /**
     * List rows for a website, joined to region names for display.
     */
    public function getList($websiteId = null)
    {
        return $this->prices->listForWebsite($websiteId);
    }

    public function getRow($id, $websiteId = null)
    {
        return $this->prices->findForWebsite($id, $websiteId);
    }

    public function create(array $params)
    {
        $params = $this->normalize($params);

        if ($this->duplicateExists($params['website_id'], $params['city_code'], $params['district_code'], $params['subdistrict_code'])) {
            return ['status' => 'failed', 'message' => 'A delivery price for this area already exists.'];
        }

        DB::beginTransaction();
        $row = $this->prices->create($params);
        if (!$row) {
            DB::rollBack();
            return ['status' => 'failed', 'message' => 'Failed to create delivery price'];
        }
        DB::commit();

        return ['status' => 'success', 'message' => 'Delivery price created successfully', 'data' => $row];
    }

    public function update($id, array $params)
    {
        $row = $this->prices->find($id);
        if (!$row) {
            return ['status' => 'failed', 'message' => 'Delivery price not found'];
        }

        $params = $this->normalize($params);

        if ($this->duplicateExists($params['website_id'] ?? $row->website_id, $params['city_code'], $params['district_code'], $params['subdistrict_code'], $id)) {
            return ['status' => 'failed', 'message' => 'A delivery price for this area already exists.'];
        }

        DB::beginTransaction();
        $updated = $this->prices->update($row, $params);
        if (!$updated) {
            DB::rollBack();
            return ['status' => 'failed', 'message' => 'Failed to update delivery price'];
        }
        DB::commit();

        return ['status' => 'success', 'message' => 'Delivery price updated successfully', 'data' => $row];
    }

    public function delete($id)
    {
        $row = $this->prices->find($id);
        if (!$row) {
            return ['status' => 'failed', 'message' => 'Delivery price not found'];
        }
        $this->prices->delete($row);
        return ['status' => 'success', 'message' => 'Delivery price deleted successfully'];
    }

    /**
     * Normalize blank district/subdistrict to null so the "level" is unambiguous.
     * A subdistrict without a district makes no sense, so clear the deeper level
     * whenever a shallower one is empty.
     */
    private function normalize(array $params): array
    {
        $params['district_code']    = $params['district_code']    ?? null;
        $params['subdistrict_code'] = $params['subdistrict_code'] ?? null;

        if ($params['district_code'] === '') $params['district_code'] = null;
        if ($params['subdistrict_code'] === '') $params['subdistrict_code'] = null;

        if (empty($params['district_code'])) {
            $params['subdistrict_code'] = null; // no district => cannot have subdistrict
        }

        return $params;
    }

    /**
     * MySQL treats NULLs as distinct in a UNIQUE index, so uniqueness of the
     * (website, city, district, subdistrict) tuple is enforced here too.
     */
    private function duplicateExists($websiteId, $cityCode, $districtCode, $subdistrictCode, $ignoreId = null): bool
    {
        return $this->prices->areaTaken($websiteId, $cityCode, $districtCode, $subdistrictCode, $ignoreId);
    }

    /* =========================================================
     * REGION LOOKUPS (for cascading selects)
     * ========================================================= */

    public function provinces()
    {
        return $this->regions->provinces();
    }

    public function citiesByProvince(string $provinceCode)
    {
        return $this->regions->citiesByProvince($provinceCode);
    }

    /**
     * Look up the province_code that a given city belongs to (for edit hydration).
     */
    public function provinceOfCity(?string $cityCode): ?string
    {
        return $this->regions->provinceCodeOfCity($cityCode);
    }

    public function districtsByCity(string $cityCode)
    {
        return $this->regions->districtsByCity($cityCode);
    }

    public function subdistrictsByDistrict(string $districtCode)
    {
        return $this->regions->subdistrictsByDistrict($districtCode);
    }

    /* =========================================================
     * FALLBACK RESOLVER: subdistrict -> district -> city
     *
     * The fallback itself is the rule -- charge the most specific fare that
     * has been set, and read a missing level as "priced above" rather than as
     * free. Each step is one repository lookup.
     * ========================================================= */

    /**
     * Resolve the fare directly from a known code chain (city required,
     * district/subdistrict optional). Picks the most specific price that
     * exists, falling back subdistrict -> district -> city. Unlike
     * resolveBySubdistrict(), this does not re-read the glb_* hierarchy, so it
     * works even when the subdistrict table is not seeded.
     */
    public function resolveFare(int $websiteId, ?string $cityCode, ?string $districtCode = null, ?string $subdistrictCode = null): ?DeliveryPrice
    {
        if (empty($cityCode)) return null;

        $districtCode    = $districtCode    ?: null;
        $subdistrictCode = $subdistrictCode ?: null;

        if ($subdistrictCode && $districtCode) {
            $row = $this->prices->activeForArea($websiteId, $cityCode, $districtCode, $subdistrictCode);
            if ($row) return $row;
        }

        if ($districtCode) {
            $row = $this->prices->activeForArea($websiteId, $cityCode, $districtCode, null);
            if ($row) return $row;
        }

        return $this->resolveByCity($websiteId, $cityCode);
    }

    /**
     * Effective price for a subdistrict, falling back to district then city.
     * Returns the winning DeliveryPrice model, or null.
     */
    public function resolveBySubdistrict(int $websiteId, string $subdistrictCode): ?DeliveryPrice
    {
        $region = $this->regions->locateSubdistrict($subdistrictCode);

        if (!$region) return null;

        // 1) exact subdistrict price
        $row = $this->prices->activeForArea($websiteId, $region->city_code, $region->district_code, $region->subdistrict_code);
        if ($row) return $row;

        // 2) district price
        if ($region->district_code) {
            $row = $this->prices->activeForArea($websiteId, $region->city_code, $region->district_code, null);
            if ($row) return $row;
        }

        // 3) city price
        return $this->resolveByCity($websiteId, $region->city_code);
    }

    /**
     * Effective price for a district, falling back to city.
     */
    public function resolveByDistrict(int $websiteId, string $districtCode): ?DeliveryPrice
    {
        $region = $this->regions->locateDistrict($districtCode);

        if (!$region) return null;

        $row = $this->prices->activeForArea($websiteId, $region->city_code, $region->district_code, null);
        if ($row) return $row;

        return $this->resolveByCity($websiteId, $region->city_code);
    }

    /**
     * Price for a city (no fallback below city).
     */
    public function resolveByCity(int $websiteId, string $cityCode): ?DeliveryPrice
    {
        return $this->prices->activeForArea($websiteId, $cityCode, null, null);
    }
}
