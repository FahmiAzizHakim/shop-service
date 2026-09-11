<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Package lines of a transaction. A package carries the money: its
     * price is the full packages.package_price and its discount the
     * packages.package_discount (both snapshotted at time of sale, so the
     * receipt stays correct if the package is later re-priced or deleted).
     *
     * What the package contains is exploded into the other tables at price 0:
     * products -> transaction_details, charges -> transaction_charges,
     * benefits -> transaction_benefits.
     *
     *   subtotal = (price * qty) - discount
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();

            $table->unsignedBigInteger('package_id')->nullable()->index();

            // Snapshots at time of sale.
            $table->string('package_code', 60)->nullable();
            $table->string('package_name')->nullable();

            $table->integer('qty')->default(1);
            $table->decimal('price', 14, 2)->default(0);        // unit price, before discount
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
        Schema::dropIfExists('transaction_packages');
    }
}
