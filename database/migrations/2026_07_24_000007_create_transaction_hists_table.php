<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionHistsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Append-only audit trail of status changes for a transaction. Each row is
     * one transition: the status it moved to, a human description of the change,
     * and who did it. status_code references codes.code where parentcode = 'STS'.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_hists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();

            $table->string('status_from', 60)->nullable();  // previous status code
            $table->string('status_code', 60)->nullable();  // new status code (STS*)
            $table->string('status_name')->nullable();       // snapshot of the code name
            $table->text('description')->nullable();          // note about the change

            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();

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
        Schema::dropIfExists('transaction_hists');
    }
}
