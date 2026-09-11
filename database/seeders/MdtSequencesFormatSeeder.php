<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MdtSequencesFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $data = [
            'sequence_name' => "Transaction", 
            'sequence_prefix' => 'INV-', 
            'description' => 'Transaction Invoice',
            'sequence_length' => 6,
            'sequence_format1' => null,
            'sequence_format2' => 'sequence_prefix',
            'sequence_format3' => 'year',
            'sequence_format4' => 'month',
            'sequence_format5' => 'number',
            'monthly_reset' => 1,
        ];
  
        DB::table('mdt_sequences_format')->insert($data);
    }
}
