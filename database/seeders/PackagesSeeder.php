<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The packages of the catalog websites and their contents, snapshotted from
     * the live database.
     *
     * Each package's lines are rebuilt from natural keys -- a product by its
     * products_code, a charge by its (website, code), a benefit by its text --
     * so the ids may be renumbered without the contents ever pointing at the
     * wrong thing. 'service' is the service the package is listed under, or null
     * when it is not tied to one.
     *
     * package_details has no cascade, so the old lines go before the packages.
     *
     * Resets the package catalog only. Packages already sold are snapshotted
     * onto transaction_packages, so placed orders and receipts are unaffected.
     *
     * Depends on ServicesSeeder, ProductsSeeder and OtherChargesSeeder.
     *
     * @return void
     */
    public function run()
    {
        $packages = [
            [
                'website_id'          => 3,
                'service'             => 3,
                'package_name'        => 'Pemasangan Home Charging(EV Power) - 7KW',
                'package_code'        => 'PKG-HC-7KW',
                'package_price'       => '11500000.00',
                'package_discount'    => '2000000.00',
                'package_description' => null,
                'remark'              => null,
                'is_active'           => true,
                'contents'            => [
                    [
                        'qty'     => 1,
                        'product' => 'EV-HC-7KW',
                    ],
                    [
                        'qty'    => 1,
                        'charge' => 'HCI',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Barang 24 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Pemasangan 2 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Konsultasi Produk Home Charging',
                    ],
                ],
            ],
            [
                'website_id'          => 3,
                'service'             => 3,
                'package_name'        => 'Pemasangan Home Charging(EV Power) - 11KW',
                'package_code'        => 'PKG-HC-11KW',
                'package_price'       => '14150000.00',
                'package_discount'    => '2000000.00',
                'package_description' => null,
                'remark'              => null,
                'is_active'           => true,
                'contents'            => [
                    [
                        'qty'     => 1,
                        'product' => 'EV-HC-11KW',
                    ],
                    [
                        'qty'    => 1,
                        'charge' => 'HCI',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Barang 24 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Pemasangan 2 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Konsultasi Produk Home Charging EV',
                    ],
                ],
            ],
            [
                'website_id'          => 3,
                'service'             => 'Public Charging / SPKLU',
                'package_name'        => 'Pemasangan Public Charging / SPKLU',
                'package_code'        => 'PKG-SPKLU',
                // Priced on survey: 0 makes the card show "Hubungi Kami".
                'package_price'       => '0.00',
                'package_discount'    => '0.00',
                'package_description' => null,
                'remark'              => null,
                'is_active'           => true,
                'contents'            => [
                    [
                        'qty'     => 1,
                        'product' => 'EV-SPKLU',
                    ],
                    [
                        'qty'    => 1,
                        'charge' => 'HCI',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Barang 24 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Garansi Pemasangan 2 Bulan',
                    ],
                    [
                        'qty'     => 1,
                        'benefit' => 'Konsultasi Produk Public Charging / SPKLU',
                    ],
                ],
            ],
        ];

        $websiteIds = array_values(array_unique(array_column($packages, 'website_id')));

        // ---- reset: detail lines first, they have no cascade ----
        $oldIds = DB::table('packages')->whereIn('website_id', $websiteIds)->pluck('id');

        if ($oldIds->isNotEmpty()) {
            DB::table('package_details')->whereIn('package_id', $oldIds)->delete();
            DB::table('packages')->whereIn('id', $oldIds)->delete();

            $this->command->warn('  cleared ' . $oldIds->count() . ' old package(s)');
        }

        if (DB::table('packages')->count() === 0) {
            DB::statement('ALTER TABLE `packages` AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE `package_details` AUTO_INCREMENT = 1');
        }

        foreach ($packages as $pkg) {
            $serviceId = $pkg['service']
                ? DB::table('services')
                    ->where('website_id', $pkg['website_id'])
                    ->where('service_name', $pkg['service'])
                    ->value('id')
                : null;

            $packageId = DB::table('packages')->insertGetId([
                'website_id'          => $pkg['website_id'],
                'service_id'          => $serviceId,
                'package_name'        => $pkg['package_name'],
                'package_code'        => $pkg['package_code'],
                'package_price'       => $pkg['package_price'],
                'package_discount'    => $pkg['package_discount'],
                'package_description' => $pkg['package_description'],
                'remark'              => $pkg['remark'],
                'is_active'           => $pkg['is_active'],
            ]);

            $lines = 0;

            foreach ($pkg['contents'] as $line) {
                $row = [
                    'package_id'      => $packageId,
                    'product_id'      => null,
                    'other_charge_id' => null,
                    'other_benefit'   => null,
                    'qty'             => $line['qty'] ?? 1,
                ];

                if (isset($line['product'])) {
                    $row['product_id'] = DB::table('products')
                        ->where('products_code', $line['product'])
                        ->value('id');

                    if (!$row['product_id']) {
                        $this->command->warn("    product '{$line['product']}' missing, line skipped");
                        continue;
                    }
                } elseif (isset($line['charge'])) {
                    $row['other_charge_id'] = DB::table('other_charges')
                        ->where('website_id', $pkg['website_id'])
                        ->where('code', $line['charge'])
                        ->value('id');

                    if (!$row['other_charge_id']) {
                        $this->command->warn("    charge '{$line['charge']}' missing, line skipped");
                        continue;
                    }
                } else {
                    $row['other_benefit'] = $line['benefit'];
                }

                DB::table('package_details')->insert($row);
                $lines++;
            }

            $net = number_format((float) $pkg['package_price'] - (float) $pkg['package_discount'], 0, ',', '.');
            $this->command->info(
                "  website {$pkg['website_id']}: {$pkg['package_code']} net Rp $net, $lines line(s)"
            );
        }
    }
}
