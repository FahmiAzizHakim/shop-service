<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Line items of a transaction. Product / variant references are kept for
     * traceability, but name/code/price are snapshotted so the receipt stays
     * correct even if the catalogue changes or a product is later deleted.
     *
     *   subtotal = (price * qty) - discount
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();

            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();

            // Snapshots at time of sale.
            $table->string('product_code', 60)->nullable();
            $table->string('product_name')->nullable();
            $table->string('variant_name')->nullable();
            $table->string('uom', 60)->nullable();              // codes.code (UOM*)

            $table->integer('qty')->default(1);
            $table->decimal('price', 14, 2)->default(0);        // unit price
            $table->decimal('discount', 14, 2)->default(0);     // line discount
            $table->decimal('subtotal', 14, 2)->default(0);

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
        Schema::dropIfExists('transaction_details');
    }
}
