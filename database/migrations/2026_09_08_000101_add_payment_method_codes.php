<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The two ways a customer can pay: manual transfer, or QRIS.
     *
     * transactions.payment_type holds a codes.code whose parentcode is 'TRT',
     * so these are the rows that let a receipt print "QRIS" rather than the
     * raw code. The pair that was there before (Cash, Credit) described a
     * counter sale and neither fits a storefront order.
     *
     * Declared in CodesSeeder as well, and that is not redundant: CodesSeeder
     * deletes the whole codes table before re-inserting it, so a row that
     * exists only as a migration disappears the next time anyone seeds. This
     * migration is for databases that already exist; the seeder is what keeps
     * them there afterwards.
     *
     * Idempotent, and safe in either order: on an empty database this inserts
     * the two rows and CodesSeeder's own delete-then-insert leaves exactly one
     * copy of each.
     */
    public function up(): void
    {
        $codes = [
            [
                'parentcode'  => 'TRT',
                'code'        => 'TRTMN',
                'name'        => 'Transfer Manual',
                'description' => 'Transfer ke rekening bank, dengan bukti pembayaran diunggah pembeli',
                'value'       => null,
                'datatype'    => 'STRING',
                'order'       => null,
            ],
            [
                'parentcode'  => 'TRT',
                'code'        => 'TRTQR',
                'name'        => 'QRIS',
                'description' => 'Bayar dengan memindai QRIS',
                'value'       => null,
                'datatype'    => 'STRING',
                'order'       => null,
            ],
        ];

        foreach ($codes as $code) {
            if (DB::table('codes')->where('code', $code['code'])->exists()) {
                continue;
            }

            DB::table('codes')->insert($code);
        }
    }

    public function down(): void
    {
        DB::table('codes')->whereIn('code', ['TRTMN', 'TRTQR'])->delete();
    }
};
