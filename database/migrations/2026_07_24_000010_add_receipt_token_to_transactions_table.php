<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceiptTokenToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Random, unguessable token used for the public receipt URL
     * (/installer/receipt/{token}) so a customer can revisit their receipt
     * without exposing sequential transaction ids.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receipt_token', 64)->nullable()->unique()->after('receipt_no');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['receipt_token']);
            $table->dropColumn('receipt_token');
        });
    }
}
