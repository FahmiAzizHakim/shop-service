<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * The services of each catalog website, snapshotted from the live database.
     *
     * This is a real reset: the old services of these websites are deleted, and
     * because nothing in the schema cascades, everything hanging under them goes
     * first -- products and their variants, images, categories and
     * specifications. Those are re-created by the seeders that run next.
     *
     * Websites absent from the list below (website 1, which has no catalog) are
     * left untouched, as are transactions, banks, delivery prices and carts.
     *
     * @return void
     */
    public function run()
    {
        $tree = [
            2 => [
                [
                    'service_name'        => 'Furniture',
                    'service_title'       => 'Furniture installation & cleaning',
                    'service_subtitle'    => 'Rapi, cepat, dan aman',
                    'service_description' => 'Perakitan, pembongkaran dan pembersihan furniture (lemari, meja, kursi, rak) oleh teknisi berpengalaman dengan peralatan lengkap.',
                    'service_image'       => null,
                    'service_icon'        => 'bi-hammer',
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'service_name'        => 'AC',
                    'service_title'       => 'AC installation & cleaning',
                    'service_subtitle'    => 'AC dingin kembali maksimal',
                    'service_description' => 'Pasang baru, bongkar pasang, dan cuci AC untuk rumah maupun kantor. Dikerjakan cepat dan bergaransi.',
                    'service_image'       => null,
                    'service_icon'        => 'bi-snow',
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
            3 => [
                [
                    'service_name'        => 'Home Charging',
                    'service_title'       => 'Pengisian daya EV di rumah',
                    'service_subtitle'    => 'Aman, standar, bergaransi',
                    'service_description' => 'Instalasi home charging untuk kendaraan listrik, dikerjakan sesuai standar kelistrikan oleh teknisi bersertifikat.',
                    'service_image'       => null,
                    'service_icon'        => 'bi-house-gear',
                    'remark'              => null,
                    'is_active'           => true,
                ],
                [
                    'service_name'        => 'Public Charging / SPKLU',
                    'service_title'       => 'SPKLU & pengisian daya publik',
                    'service_subtitle'    => 'Untuk area komersial & publik',
                    'service_description' => 'Penyediaan dan instalasi SPKLU / stasiun pengisian kendaraan listrik untuk area publik dan komersial.',
                    'service_image'       => null,
                    'service_icon'        => 'bi-ev-station',
                    'remark'              => null,
                    'is_active'           => true,
                ],
            ],
        ];

        // Clear every catalog website first, so the tables are empty before
        // anything is inserted and the numbering can start over.
        foreach (array_keys($tree) as $websiteId) {
            $this->reset((int) $websiteId);
        }

        $this->restartNumbering();

        foreach ($tree as $websiteId => $services) {
            foreach ($services as $row) {
                DB::table('services')->insert($row + ['website_id' => $websiteId]);
            }

            $this->command->info("  website $websiteId services: " . implode(', ', array_column($services, 'service_name')));
        }
    }

    /**
     * Drop one website's services and the whole product subtree beneath them.
     */
    private function reset(int $websiteId): void
    {
        $serviceIds = DB::table('services')->where('website_id', $websiteId)->pluck('id');

        if ($serviceIds->isEmpty()) {
            return;
        }

        $productIds = DB::table('products')->whereIn('service_id', $serviceIds)->pluck('id');

        if ($productIds->isNotEmpty()) {
            DB::table('product_variants')->whereIn('product_id', $productIds)->delete();
            DB::table('product_images')->whereIn('product_id', $productIds)->delete();
            DB::table('product_categories')->whereIn('product_id', $productIds)->delete();
            DB::table('product_specifications')->whereIn('product_id', $productIds)->delete();
            DB::table('products')->whereIn('id', $productIds)->delete();
        }

        DB::table('services')->whereIn('id', $serviceIds)->delete();

        $this->command->warn(
            "  website $websiteId: cleared " . $serviceIds->count() . ' service(s) and '
            . $productIds->count() . ' product(s)'
        );
    }

    /**
     * Restart the ids of any catalog table that is now completely empty.
     */
    private function restartNumbering(): void
    {
        foreach (['product_variants', 'product_images', 'product_categories', 'product_specifications', 'products', 'services'] as $table) {
            if (DB::table($table)->count() === 0) {
                DB::statement("ALTER TABLE `$table` AUTO_INCREMENT = 1");
            }
        }
    }
}
