<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed shop-service.
     *
     * Two halves:
     *
     *  - Reference data (codes, sequences, regions, couriers). Slow, and only
     *    a fresh install needs it -- these seeders insert unconditionally.
     *  - SiteStructureSeeder: the catalog of each website. Safe to re-run on a
     *    live database:
     *        php artisan db:seed --class=SiteStructureSeeder
     */
    public function run(): void
    {
        // ---- Reference data (fresh install only) ----
        $this->call(CodesSeeder::class);
        $this->call(MdtSequencesFormatSeeder::class);
        $this->call(RajaongkirmapTableSeeder::class);
        $this->call(GlbCountriesTableSeeder::class);
        $this->call(GlbProvincesTableSeeder::class);
        $this->call(GlbCitiesTableSeeder::class);
        $this->call(GlbDistrictsTableSeeder::class);
        $this->call(CouriersTableSeeder::class);

        // ---- Services, products and packages ----
        $this->call(SiteStructureSeeder::class);
    }
}
