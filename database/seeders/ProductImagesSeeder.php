<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductImagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Product photos, keyed on products_code and snapshotted from the live
     * database. image_url is a path under the public docroot, so the files
     * themselves live in public/uploads/product/ -- copy that folder along with
     * the database when moving the site, or the rows will point at nothing and
     * the catalog falls back to its icon placeholder.
     *
     * Depends on ProductsSeeder having run first.
     *
     * @return void
     */
    public function run()
    {
        $images = [
            'FUR-INSTALL'  => [
                [
                    'image_name'        => 'c7f0e96b8a936b7aaf0de85f44006401.jpg',
                    'image_url'         => 'uploads/product/c7f0e96b8a936b7aaf0de85f44006401-20260824002039-24DhtQ.jpg',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'FUR-CLEANING' => [
                [
                    'image_name'        => 'f14710fffcaf6740b450afca4e20a754.jpg',
                    'image_url'         => 'uploads/product/f14710fffcaf6740b450afca4e20a754-20260824001931-KwdpsM.jpg',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'AC-INSTALL'   => [
                [
                    'image_name'        => 'ENERGY-man-installing-new-ac-shutterstock_291265688-e1534370186720.jpg',
                    'image_url'         => 'uploads/product/energy-man-installing-new-ac-shutterstock-291265688-e1534370186720-20260824015645-yJUjzH.jpg',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'AC-CLEANING'  => [
                [
                    'image_name'        => '2cadf30425f901a347c2d47bf80ae488.jpg',
                    'image_url'         => 'uploads/product/2cadf30425f901a347c2d47bf80ae488-20260824015540-f81FqS.jpg',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'EV-HC-7KW'    => [
                [
                    'image_name'        => 'HC 7 Kw.png',
                    'image_url'         => 'uploads/product/hc-7-kw-20260824020308-RZyWjy.png',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'EV-HC-11KW'   => [
                [
                    'image_name'        => 'HC 11 Kw.png',
                    'image_url'         => 'uploads/product/hc-11-kw-20260824020252-i1KEgk.png',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
            'EV-SPKLU'     => [
                [
                    'image_name'        => 'WhatsApp Image 2026-08-28 at 11.21.56 AM.jpeg',
                    'image_url'         => 'uploads/product/whatsapp-image-2026-08-28-at-112156-am-20260828042328-QTW0lX.jpeg',
                    'image_description' => null,
                    'order'             => 1,
                    'is_active'         => true,
                ],
            ],
        ];

        $productIds = [];

        foreach (array_keys($images) as $code) {
            $productIds[$code] = DB::table('products')->where('products_code', $code)->value('id');
        }

        DB::table('product_images')->whereIn('product_id', array_filter($productIds))->delete();

        if (DB::table('product_images')->count() === 0) {
            DB::statement('ALTER TABLE `product_images` AUTO_INCREMENT = 1');
        }

        $missing = 0;

        foreach ($images as $code => $rows) {
            $productId = $productIds[$code] ?? null;

            if (!$productId) {
                $this->command->warn("  product '$code' missing, images skipped");
                continue;
            }

            foreach ($rows as $row) {
                DB::table('product_images')->insert($row + ['product_id' => $productId]);

                if (!file_exists(public_path($row['image_url']))) {
                    $missing++;
                }
            }

            $this->command->info("  $code: " . count($rows) . ' image(s)');
        }

        if ($missing) {
            $this->command->warn("  $missing image file(s) not found under public/ -- copy public/uploads/product/");
        }
    }
}
