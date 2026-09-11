<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSpecificationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The specification rows shown on each product in the catalog, keyed on
     * products_code and snapshotted from the live database.
     *
     * Depends on ProductsSeeder having run first.
     *
     * @return void
     */
    public function run()
    {
        $specs = [
            'EV-HC-7KW'  => [
                [
                    'attribute' => 'Input power',
                    'value'     => '85V - 264V - 380V',
                ],
                [
                    'attribute' => 'Charging current',
                    'value'     => '8A/10A/13A/16A/32A adjustable',
                ],
                [
                    'attribute' => 'Output power',
                    'value'     => '0-7KW',
                ],
                [
                    'attribute' => 'Protection level',
                    'value'     => 'IP66(working state)',
                ],
                [
                    'attribute' => 'Cable length',
                    'value'     => '5 meters',
                ],
            ],
            'EV-HC-11KW' => [
                [
                    'attribute' => 'Input power',
                    'value'     => '85V - 264V - 380V',
                ],
                [
                    'attribute' => 'Charging current',
                    'value'     => '8A/10A/13A/16A adjustable',
                ],
                [
                    'attribute' => 'Output power',
                    'value'     => '11KW',
                ],
                [
                    'attribute' => 'Protection level',
                    'value'     => 'IP66(working state)',
                ],
                [
                    'attribute' => 'Cable length',
                    'value'     => '5 meters',
                ],
            ],
        ];

        $productIds = [];

        foreach (array_keys($specs) as $code) {
            $productIds[$code] = DB::table('products')->where('products_code', $code)->value('id');
        }

        DB::table('product_specifications')->whereIn('product_id', array_filter($productIds))->delete();

        if (DB::table('product_specifications')->count() === 0) {
            DB::statement('ALTER TABLE `product_specifications` AUTO_INCREMENT = 1');
        }

        foreach ($specs as $code => $rows) {
            $productId = $productIds[$code] ?? null;

            if (!$productId) {
                $this->command->warn("  product '$code' missing, specifications skipped");
                continue;
            }

            foreach ($rows as $row) {
                DB::table('product_specifications')->insert($row + ['product_id' => $productId]);
            }

            $this->command->info("  $code: " . count($rows) . ' specification(s)');
        }
    }
}
