<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Miscellaneous charges applied to a transaction, sourced from the
     * other_charges master. code/name are snapshotted; amount is the value
     * actually billed (may differ from the master price at time of sale).
     * The sum of amounts is mirrored on transactions.charges.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();
            $table->unsignedBigInteger('other_charge_id')->nullable()->index();

            $table->string('code', 60)->nullable();     // snapshot
            $table->string('name')->nullable();         // snapshot
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('remark')->nullable();

            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_charges');
    }
}
