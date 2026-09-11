<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeliveryPricesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * A starter delivery-price list for the Jakarta area, so the table is not
     * empty and the admin screen has something to show the shape of.
     *
     * Every row is seeded INACTIVE, and that is the point. The prices below are
     * placeholders -- round numbers, not anyone's real tariff -- and
     * DeliveryPriceService::resolveFare() only looks at active rows, so nothing
     * here can quote a made-up figure to a customer. Enable a row in the admin
     * once its real price is set.
     *
     * Note what that means while they are all off: CheckoutService now refuses
     * an order it cannot price, so checkout stays closed until at least the
     * destinations you serve are priced and switched on. That is deliberate --
     * a silent zero fare was the alternative, and it ships at our expense.
     *
     * Two levels are shown on purpose, because the resolution order is the part
     * worth understanding: a district row beats the city row it sits in
     * (subdistrict -> district -> city). Jakarta Selatan has both, so it
     * demonstrates the narrowing; the other cities have a city-level row only.
     *
     * Codes are the real ones from glb_cities / glb_districts -- a code that
     * does not match those tables can never resolve.
     *
     * Keyed on the full address tuple, so a re-run tops the list up and
     * corrects a price without duplicating rows or reactivating one that was
     * deliberately switched off... except is_active, which IS rewritten: treat
     * a re-seed as a reset of these starter rows.
     *
     * @return void
     */
    public function run()
    {
        // website_id => rows. Only website 1 (GLS Contract Logistic) for now;
        // the other two sites are a different company with their own areas.
        $prices = [
            1 => [
                // City level.
                ['city_code' => 'JK.01.JKT', 'district_code' => null, 'price' => 25000],
                ['city_code' => 'JK.02.JKT', 'district_code' => null, 'price' => 25000],
                ['city_code' => 'JK.03.JKT', 'district_code' => null, 'price' => 25000],
                ['city_code' => 'JK.04.JKT', 'district_code' => null, 'price' => 30000],
                ['city_code' => 'JK.05.JKT', 'district_code' => null, 'price' => 30000],
                // Kepulauan Seribu is a boat trip, not a van.
                ['city_code' => 'JK.06.JKT', 'district_code' => null, 'price' => 150000],

                // District level, to show the narrowing: these beat the
                // JK.03.JKT row above for addresses inside them.
                ['city_code' => 'JK.03.JKT', 'district_code' => 'JK.03.01.JKT', 'price' => 20000],
                ['city_code' => 'JK.03.JKT', 'district_code' => 'JK.03.03.JKT', 'price' => 18000],
            ],
        ];

        $unknown = 0;

        foreach ($prices as $websiteId => $rows) {
            foreach ($rows as $row) {
                DB::table('delivery_prices')->updateOrInsert(
                    [
                        'website_id'       => $websiteId,
                        'city_code'        => $row['city_code'],
                        'district_code'    => $row['district_code'],
                        'subdistrict_code' => null,
                    ],
                    [
                        'price'      => $row['price'],
                        'is_active'  => false,
                        'created_by' => 'seeder',
                    ]
                );

                // A code that is not in the region tables would be a row that
                // can never match an address, so it is worth saying so loudly.
                $exists = $row['district_code']
                    ? DB::table('glb_districts')->where('district_code', $row['district_code'])->exists()
                    : DB::table('glb_cities')->where('city_code', $row['city_code'])->exists();

                if (!$exists) {
                    $unknown++;
                    $this->command->warn('  unknown region code: ' . ($row['district_code'] ?: $row['city_code']));
                }
            }

            $this->command->info("  website $websiteId delivery prices: " . count($rows) . ' (all inactive)');
        }

        if (!$unknown) {
            $this->command->info('  all region codes check out against glb_cities / glb_districts');
        }

        $this->command->warn('  prices are placeholders and every row is OFF -- set real prices and enable them before taking orders');
    }
}
