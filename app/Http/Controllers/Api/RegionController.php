<?php

namespace App\Http\Controllers\Api;

use App\Services\Commerce\DeliveryPriceService;
use Illuminate\Http\Request;

/**
 * Region lookups for the checkout address form, and the fare they resolve to.
 *
 * Public: a visitor fills the address before they have any identity. The admin
 * fare editor reads the same lists, so the two cascades cannot drift apart.
 *
 * These read the glb_* tables held in this database. The live third-party
 * lookups (RajaOngkir tariffs, tracking) are thirdparty-service's.
 */
class RegionController extends ApiController
{
    protected $delivery;

    public function __construct(DeliveryPriceService $delivery)
    {
        $this->delivery = $delivery;
    }

    public function provinces()
    {
        return $this->items($this->delivery->provinces());
    }

    public function cities($provinceCode)
    {
        return $this->items($this->delivery->citiesByProvince($provinceCode));
    }

    public function districts($cityCode)
    {
        return $this->items($this->delivery->districtsByCity($cityCode));
    }

    public function subdistricts($districtCode)
    {
        return $this->items($this->delivery->subdistrictsByDistrict($districtCode));
    }

    /**
     * The delivery fare for the chosen area, applying the
     * subdistrict -> district -> city fallback.
     */
    public function fare(Request $request, $website)
    {
        $row = $this->delivery->resolveFare(
            (int) $website,
            $request->query('city_code'),
            $request->query('district_code'),
            $request->query('subdistrict_code')
        );

        return response()->json([
            'data' => [
                'found' => (bool) $row,
                'fare'  => $row ? (float) $row->price : null,
                // Which level the price was found at, so a form can say so.
                'level' => $row ? $row->level : null,
            ],
        ]);
    }
}
