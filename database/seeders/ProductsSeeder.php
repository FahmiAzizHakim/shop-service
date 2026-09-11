<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The products of each catalog service, snapshotted from the live database.
     *
     * Services are matched by exact name, not LIKE, so a renamed service can
     * never silently collect another one's products. Each service's existing
     * products (and their variants, images, categories and specifications) are
     * deleted before the new set is inserted, which also makes this seeder safe
     * to run on its own -- but the seeders that rebuild those children must run
     * after it, which SiteStructureSeeder takes care of.
     *
     * @return void
     */
    public function run()
    {
        $tree = [
            2 => [
                'Furniture' => [
                    [
                        'products_name'        => 'Installation',
                        'products_code'        => 'FUR-INSTALL',
                        'products_price'       => '50000.00',
                        'products_description' => 'Jasa perakitan / pemasangan furniture. Harga mengikuti ukuran yang dipilih.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                    [
                        'products_name'        => 'Cleaning',
                        'products_code'        => 'FUR-CLEANING',
                        'products_price'       => '75000.00',
                        'products_description' => 'Jasa pembersihan furniture. Harga mengikuti ukuran yang dipilih.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                ],
                'AC'        => [
                    [
                        'products_name'        => 'Installation',
                        'products_code'        => 'AC-INSTALL',
                        'products_price'       => '200000.00',
                        'products_description' => 'Jasa pemasangan / bongkar pasang AC.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                    [
                        'products_name'        => 'Cleaning',
                        'products_code'        => 'AC-CLEANING',
                        'products_price'       => '80000.00',
                        'products_description' => 'Jasa cuci / cleaning AC.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                ],
            ],
            3 => [
                'Home Charging'           => [
                    [
                        'products_name'        => 'Home Charging 7 KW',
                        'products_code'        => 'EV-HC-7KW',
                        'products_price'       => '9500000.00',
                        'products_description' => 'Home charging 7 KW, termasuk instalasi oleh teknisi bersertifikat.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                    [
                        'products_name'        => 'Home Charging 11 KW',
                        'products_code'        => 'EV-HC-11KW',
                        'products_price'       => '12150000.00',
                        'products_description' => 'Home charging 11 KW, termasuk instalasi oleh teknisi bersertifikat.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                ],
                'Public Charging / SPKLU' => [
                    [
                        'products_name'        => 'Public Charging/SPKLU',
                        'products_code'        => 'EV-SPKLU',
                        'products_price'       => '0.00',
                        'products_description' => 'Penyediaan dan instalasi stasiun pengisian kendaraan listrik (SPKLU) untuk area publik dan komersial. Harga mengikuti hasil survei lokasi, kapasitas daya dan jumlah unit.',
                        'products_weight'      => null,
                        'products_width'       => null,
                        'products_length'      => null,
                        'products_height'      => null,
                        'remark'               => null,
                        'is_active'            => true,
                    ],
                ],
            ],
        ];

        // Resolve the services once, clear them all, then insert -- so the
        // tables are empty in between and the numbering can start over.
        $serviceIds = [];

        foreach ($tree as $websiteId => $services) {
            foreach (array_keys($services) as $serviceName) {
                $serviceIds[$websiteId][$serviceName] = DB::table('services')
                    ->where('website_id', $websiteId)
                    ->where('service_name', $serviceName)
                    ->value('id');
            }
        }

        foreach ($serviceIds as $byName) {
            foreach (array_filter($byName) as $serviceId) {
                $this->reset($serviceId);
            }
        }

        $this->restartNumbering();

        foreach ($tree as $websiteId => $services) {
            foreach ($services as $serviceName => $products) {
                $serviceId = $serviceIds[$websiteId][$serviceName] ?? null;

                if (!$serviceId) {
                    $this->command->warn("  website $websiteId: service '$serviceName' missing, products skipped");
                    continue;
                }

                foreach ($products as $row) {
                    DB::table('products')->insert($row + ['service_id' => $serviceId]);
                }

                $this->command->info(
                    "  website $websiteId / $serviceName: " . implode(', ', array_column($products, 'products_code'))
                );
            }
        }
    }

    /**
     * Drop one service's products and everything attached to them.
     */
    private function reset($serviceId): void
    {
        $productIds = DB::table('products')->where('service_id', $serviceId)->pluck('id');

        if ($productIds->isEmpty()) {
            return;
        }

        DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
        DB::table('product_images')->whereIn('product_id', $productIds)->delete();
        DB::table('product_categories')->whereIn('product_id', $productIds)->delete();
        DB::table('product_specifications')->whereIn('product_id', $productIds)->delete();
        DB::table('products')->whereIn('id', $productIds)->delete();
    }

    /**
     * Restart the ids of any product table that is now completely empty.
     */
    private function restartNumbering(): void
    {
        foreach (['product_variants', 'product_images', 'product_categories', 'product_specifications', 'products'] as $table) {
            if (DB::table($table)->count() === 0) {
                DB::statement("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            }
        }
    }
}
