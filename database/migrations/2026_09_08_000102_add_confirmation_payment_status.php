<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The status an order sits at once the buyer says they have paid.
     *
     * Between Payment (STSPY, waiting for money) and Paid (STSPD, money seen):
     * proof has been uploaded and somebody has to look at it. Without a status
     * of its own, an order with a receipt attached is indistinguishable from
     * one nobody has paid, and the seller has no queue to work through.
     *
     * A new code rather than the existing STSCF "Confirmed": that one is the
     * seller confirming an order, and reusing it would make two different
     * things look the same in a history that already records both.
     *
     * name is English and description is Indonesian, as the rest of the STS
     * group is -- the receipt shows the description to the customer.
     *
     * Declared in CodesSeeder as well, and that is not redundant: CodesSeeder
     * deletes the codes table before re-inserting it, so a row that exists
     * only here would disappear the next time anyone seeds.
     */
    public function up(): void
    {
        if (DB::table('codes')->where('code', 'STSCP')->exists()) {
            return;
        }

        DB::table('codes')->insert([
            'parentcode'  => 'STS',
            'code'        => 'STSCP',
            'name'        => 'Confirmation Payment',
            'description' => 'Menunggu Konfirmasi Pembayaran',
            'value'       => null,
            'datatype'    => 'STRING',
            'order'       => null,
        ]);
    }

    public function down(): void
    {
        // Orders left at this status would lose the name behind their code, so
        // put them back to waiting-for-payment rather than orphaning them.
        DB::table('transactions')->where('status', 'STSCP')->update(['status' => 'STSPY']);
        DB::table('codes')->where('code', 'STSCP')->delete();
    }
};
