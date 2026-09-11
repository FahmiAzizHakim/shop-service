<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OtherChargesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Extra charges a package can include, per website, snapshotted from the
     * live database. Keyed on (website_id, code) and updated in place: package
     * lines and placed transactions both point at these rows, so they are never
     * deleted and renumbered.
     *
     * @return void
     */
    public function run()
    {
        $charges = [
            3 => [
                [
                    'code'        => 'Handling',
                    'name'        => 'Handling',
                    'description' => null,
                    'remark'      => null,
                    'price'       => '50000.00',
                    'is_active'   => true,
                ],
                [
                    'code'        => 'HCI',
                    'name'        => 'Pemasangan Home Charging',
                    'description' => 'Jasa Pemasang Home Charging Saja',
                    'remark'      => null,
                    'price'       => '2000000.00',
                    'is_active'   => true,
                ],
            ],
        ];

        foreach ($charges as $websiteId => $rows) {
            foreach ($rows as $row) {
                DB::table('other_charges')->updateOrInsert(
                    ['website_id' => $websiteId, 'code' => $row['code']],
                    $row
                );
            }

            $this->command->info("  website $websiteId charges: " . implode(', ', array_column($rows, 'code')));
        }
    }
}
