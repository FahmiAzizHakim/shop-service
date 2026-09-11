<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionResetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Empties every transaction and cart table and restarts their numbering,
     * so the shop starts from a clean slate: the next order is INV-...-000001
     * again.
     *
     * This deletes DATA only. The checkout, cart, receipt and admin
     * transaction features are untouched -- tables, models, services,
     * controllers, routes and views all stay exactly as they are, ready to
     * take orders again.
     *
     * Deliberately NOT part of SiteStructureSeeder or DatabaseSeeder: once the
     * sites are live, re-seeding the catalog must never wipe real orders. Run
     * it on purpose:
     *
     *   php artisan db:seed --class=TransactionResetSeeder
     *
     * The commerce data the transaction side reads is left alone: banks,
     * delivery prices and other charges are configuration, not order history.
     *
     * @return void
     */
    public function run()
    {
        // Children before parents -- transaction_id carries a real foreign key.
        $tables = [
            'transaction_attachments',
            'transaction_hists',
            'transaction_addresses',
            'transaction_charges',
            'transaction_benefits',
            'transaction_packages',
            'transaction_details',
            'transactions',
            'cart_items',
            'carts',
        ];

        foreach ($tables as $table) {
            $deleted = DB::table($table)->count();

            DB::table($table)->delete();

            // DELETE keeps the counter where it was; a fresh start means the
            // ids and receipt numbers begin at 1 again.
            DB::statement("ALTER TABLE `$table` AUTO_INCREMENT = 1");

            $this->command->line(sprintf('  %-26s cleared %d row(s)', $table, $deleted));
        }

        $this->command->info('  transactions and carts are empty; next receipt starts at 000001');
    }
}
