<?php

namespace App\Repositories\Reference;

use Illuminate\Support\Facades\DB;

/**
 * The glb_* region tables: provinces down to subdistricts.
 *
 * Read through the query builder rather than models -- these are seeded
 * reference rows keyed by string codes, never written from the app, and a
 * cascading select only ever wants a code and a name.
 *
 * They are copied into every service database so a lookup never has to cross a
 * service boundary. The live third-party lookups (RajaOngkir tariffs and
 * tracking) are thirdparty-service's and unrelated to these.
 *
 * The one repository here with no BaseRepository behind it: there is no model
 * to hang it on, and nothing to write.
 */
class RegionRepository
{
    public function provinces()
    {
        return DB::table('glb_provinces')
            ->where('is_active', true)
            ->orderBy('province_name')
            ->get(['province_code', 'province_name']);
    }

    public function citiesByProvince(string $provinceCode)
    {
        return DB::table('glb_cities')
            ->where('province_code', $provinceCode)
            ->where('is_active', true)
            ->orderBy('city_name')
            ->get(['city_code', 'city_name', 'city_type']);
    }

    public function districtsByCity(string $cityCode)
    {
        return DB::table('glb_districts')
            ->where('city_code', $cityCode)
            ->where('is_active', true)
            ->orderBy('district_name')
            ->get(['district_code', 'district_name']);
    }

    public function subdistrictsByDistrict(string $districtCode)
    {
        return DB::table('glb_subdistricts')
            ->where('district_code', $districtCode)
            ->where('is_active', true)
            ->orderBy('subdistrict_name')
            ->get(['subdistrict_code', 'subdistrict_name']);
    }

    /* =========================================================
     * Walking back up the chain
     * ========================================================= */

    /** Which province a city sits in -- what reopens the chain when editing. */
    public function provinceCodeOfCity(?string $cityCode): ?string
    {
        if (!$cityCode) {
            return null;
        }

        return DB::table('glb_cities')->where('city_code', $cityCode)->value('province_code');
    }

    /** A subdistrict's place in the hierarchy: its district and its city. */
    public function locateSubdistrict(string $subdistrictCode)
    {
        return DB::table('glb_subdistricts')
            ->select('subdistrict_code', 'district_code', 'city_code')
            ->where('subdistrict_code', $subdistrictCode)
            ->first();
    }

    /** A district's place in the hierarchy: its city. */
    public function locateDistrict(string $districtCode)
    {
        return DB::table('glb_districts')
            ->select('district_code', 'city_code')
            ->where('district_code', $districtCode)
            ->first();
    }

    /* =========================================================
     * Codes as names, for stamping an address onto an order
     *
     * A null code answers null rather than querying: an address is only
     * required down to district, so the levels below are legitimately absent.
     * ========================================================= */

    public function provinceName(?string $code): ?string
    {
        return $code ? DB::table('glb_provinces')->where('province_code', $code)->value('province_name') : null;
    }

    public function cityName(?string $code): ?string
    {
        return $code ? DB::table('glb_cities')->where('city_code', $code)->value('city_name') : null;
    }

    public function districtName(?string $code): ?string
    {
        return $code ? DB::table('glb_districts')->where('district_code', $code)->value('district_name') : null;
    }

    public function subdistrictName(?string $code): ?string
    {
        return $code ? DB::table('glb_subdistricts')->where('subdistrict_code', $code)->value('subdistrict_name') : null;
    }
}
