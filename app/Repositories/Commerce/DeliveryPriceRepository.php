<?php

namespace App\Repositories\Commerce;

use App\Models\DeliveryPrice;
use App\Repositories\BaseRepository;

/**
 * Delivery fares, keyed by an area at city, district or subdistrict level.
 *
 * The fallback that picks the most specific fare is a decision, so it stays in
 * DeliveryPriceService; what lives here is the single lookup each step of it
 * makes.
 */
class DeliveryPriceRepository extends BaseRepository
{
    protected $model = DeliveryPrice::class;

    /**
     * Rows for a website, joined to the region names an admin reads them by.
     *
     * The province and country are not columns on delivery_prices -- a row is
     * keyed by city and below. They are joined through the city because an
     * editor has to reopen the area chain from the top, and because an admin
     * reads an area as a path rather than as a city on its own.
     */
    public function listForWebsite($websiteId = null)
    {
        return $this->query()
            ->from('delivery_prices as dp')
            ->when($websiteId, fn ($q) => $q->where('dp.website_id', $websiteId))
            ->leftJoin('glb_cities as c', 'c.city_code', '=', 'dp.city_code')
            ->leftJoin('glb_provinces as p', 'p.province_code', '=', 'c.province_code')
            ->leftJoin('glb_districts as d', 'd.district_code', '=', 'dp.district_code')
            ->leftJoin('glb_subdistricts as s', 's.subdistrict_code', '=', 'dp.subdistrict_code')
            ->select(
                'dp.*',
                'c.country_code',
                'c.province_code',
                'p.province_name',
                'c.city_name',
                'd.district_name',
                's.subdistrict_name'
            )
            ->orderBy('p.province_name')
            ->orderBy('c.city_name')
            ->orderBy('d.district_name')
            ->orderBy('s.subdistrict_name')
            ->get();
    }

    /**
     * Whether the (website, city, district, subdistrict) tuple is already
     * taken. MySQL treats NULLs as distinct in a UNIQUE index, so this
     * constraint cannot be left to the schema.
     */
    public function areaTaken($websiteId, $cityCode, $districtCode, $subdistrictCode, $ignoreId = null): bool
    {
        return $this->query()
            ->where('website_id', $websiteId)
            ->where('city_code', $cityCode)
            ->where(fn ($q) => is_null($districtCode) ? $q->whereNull('district_code') : $q->where('district_code', $districtCode))
            ->where(fn ($q) => is_null($subdistrictCode) ? $q->whereNull('subdistrict_code') : $q->where('subdistrict_code', $subdistrictCode))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    /**
     * The active fare priced at exactly this area.
     *
     * A null district or subdistrict means the row is priced at the level
     * above, so it is matched as NULL rather than left unfiltered -- that
     * distinction is what lets the caller walk the fallback one level at a
     * time and know which level answered.
     */
    public function activeForArea(int $websiteId, string $cityCode, ?string $districtCode, ?string $subdistrictCode): ?DeliveryPrice
    {
        return $this->query()
            ->forWebsite($websiteId)
            ->active()
            ->where('city_code', $cityCode)
            ->where(fn ($q) => is_null($districtCode) ? $q->whereNull('district_code') : $q->where('district_code', $districtCode))
            ->where(fn ($q) => is_null($subdistrictCode) ? $q->whereNull('subdistrict_code') : $q->where('subdistrict_code', $subdistrictCode))
            ->first();
    }
}
