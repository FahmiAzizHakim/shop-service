<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductVariantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Variants per product, keyed on products_code and snapshotted from the live
     * database. Products with a single price have no entry here.
     *
     * The AC variants are sized in PK, the usual Indonesian rating. Their
     * prices are placeholders -- set the real ones in Commerce > Products.
     *
     * Depends on ProductsSeeder having run first.
     *
     * @return void
     */
    public function run()
    {
        $variants = [
            'FUR-INSTALL'  => [
                [
                    'variant_name'        => 'Small',
                    'variant_price'       => '50000.00',
                    'variant_description' => 'Nakas, Trolly, Office Chair, Wall Selving, Dining Chair, Sofa 1 Seat',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 50,
                    'variant_length'      => 50,
                    'variant_height'      => 80,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => 'Medium',
                    'variant_price'       => '100000.00',
                    'variant_description' => 'Sofa 2 seat & 3 Seat, Dressing Table, Sideboard TV, Dining Table, Coffee Table, Showcase, Partisi, Rak Buku, Single Bed (90, 100, 120 cm), Wardrobe 2 Pintu (<120 cm), Kitchen Set (Bottom Cabinet Only)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 100,
                    'variant_length'      => 100,
                    'variant_height'      => 100,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => 'Large',
                    'variant_price'       => '150000.00',
                    'variant_description' => 'Dining Set, Double Bed (160, 180, 200 cm), Wardrobe 3 Pintu (>120 cm – 180 cm), Kitchen Set (Top Cabinet)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 150,
                    'variant_length'      => 150,
                    'variant_height'      => 150,
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
            'FUR-CLEANING' => [
                [
                    'variant_name'        => 'Small',
                    'variant_price'       => '75000.00',
                    'variant_description' => 'Nakas, Trolly, Office Chair, Wall Selving, Dining Chair, Sofa 1 Seat',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 50,
                    'variant_length'      => 50,
                    'variant_height'      => 80,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => 'Medium',
                    'variant_price'       => '125000.00',
                    'variant_description' => 'Sofa 2 seat & 3 Seat, Dressing Table, Sideboard TV, Dining Table, Coffee Table, Showcase, Partisi, Rak Buku, Single Bed (90, 100, 120 cm), Wardrobe 2 Pintu (<120 cm), Kitchen Set (Bottom Cabinet Only)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 100,
                    'variant_length'      => 100,
                    'variant_height'      => 100,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => 'Large',
                    'variant_price'       => '175000.00',
                    'variant_description' => 'Dining Set, Double Bed (160, 180, 200 cm), Wardrobe 3 Pintu (>120 cm – 180 cm), Kitchen Set (Top Cabinet)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => 150,
                    'variant_length'      => 150,
                    'variant_height'      => 150,
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
            'AC-INSTALL'   => [
                [
                    'variant_name'        => '1/2 PK',
                    'variant_price'       => '200000.00',
                    'variant_description' => 'sampai dengan 10 m2 (setara 5.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '1 PK',
                    'variant_price'       => '250000.00',
                    'variant_description' => '10 - 18 m2 (setara 9.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '1 1/2 PK',
                    'variant_price'       => '300000.00',
                    'variant_description' => '18 - 24 m2 (setara 12.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '2 PK',
                    'variant_price'       => '350000.00',
                    'variant_description' => '24 - 36 m2 (setara 18.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
            'AC-CLEANING'  => [
                [
                    'variant_name'        => '1/2 PK',
                    'variant_price'       => '80000.00',
                    'variant_description' => 'sampai dengan 10 m2 (setara 5.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '1 PK',
                    'variant_price'       => '100000.00',
                    'variant_description' => '10 - 18 m2 (setara 9.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '1 1/2 PK',
                    'variant_price'       => '125000.00',
                    'variant_description' => '18 - 24 m2 (setara 12.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'variant_name'        => '2 PK',
                    'variant_price'       => '150000.00',
                    'variant_description' => '24 - 36 m2 (setara 18.000 BTU)',
                    'variant_color'       => null,
                    'variant_weight'      => null,
                    'variant_width'       => null,
                    'variant_length'      => null,
                    'variant_height'      => null,
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
        ];

        $productIds = [];

        foreach (array_keys($variants) as $code) {
            $productIds[$code] = DB::table('products')->where('products_code', $code)->value('id');
        }

        // Clear first, then insert, so the ids can start over.
        DB::table('product_variants')->whereIn('product_id', array_filter($productIds))->delete();

        if (DB::table('product_variants')->count() === 0) {
            DB::statement('ALTER TABLE `product_variants` AUTO_INCREMENT = 1');
        }

        foreach ($variants as $code => $rows) {
            $productId = $productIds[$code] ?? null;

            if (!$productId) {
                $this->command->warn("  product '$code' missing, variants skipped");
                continue;
            }

            foreach ($rows as $row) {
                DB::table('product_variants')->insert($row + ['product_id' => $productId]);
            }

            $this->command->info("  $code variants: " . implode(', ', array_column($rows, 'variant_name')));
        }
    }
}
