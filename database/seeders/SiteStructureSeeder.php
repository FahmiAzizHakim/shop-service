<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SiteStructureSeeder extends Seeder
{
    /**
     * The catalog each website sells:
     *
     *   1  GLS Kontrak Logistic
     *   2  Furniture/AC Installation and Cleaning
     *      |- Furniture     -> Installation (Small/Medium/Large), Cleaning (Small/Medium/Large)
     *      |- AC            -> Installation, Cleaning
     *   3  EV Charging Solution
     *      |- Home Charging -> 7 KW, 11 KW
     *      |- Public Charging / SPKLU -> Product 1, Product 2 (inactive until specified)
     *
     * Packages are listed under their service: Home Charging carries the two
     * 7 KW / 11 KW installation packages.
     *
     * Safe to re-run, and a real reset: these seeders clear the catalog of
     * those websites and build it again, so the ids come out clean.
     *
     * Nothing here touches transactions, banks, delivery prices or carts. Sold
     * packages are snapshotted onto transaction_packages, so resetting the
     * package catalog leaves placed orders and their receipts intact.
     *
     * website_id is a plain column here: the websites themselves live in
     * website-service, and the two databases only have to agree on the ids.
     *
     * Run on its own with:
     *   php artisan db:seed --class=SiteStructureSeeder
     *
     * which skips the slow reference data (codes, regions, couriers) that only
     * a fresh install needs.
     *
     * @return void
     */
    public function run()
    {
        $steps = [
            'Services'         => ServicesSeeder::class,
            'Products'         => ProductsSeeder::class,
            'Product variants' => ProductVariantsSeeder::class,
            'Product images'   => ProductImagesSeeder::class,
            'Product specs'    => ProductSpecificationsSeeder::class,
            'Other charges'    => OtherChargesSeeder::class,
            'Packages'         => PackagesSeeder::class,
            'Delivery prices'  => DeliveryPricesSeeder::class,
        ];

        foreach ($steps as $label => $class) {
            $this->command->newLine();
            $this->command->comment($label);
            $this->call($class);
        }
    }
}
