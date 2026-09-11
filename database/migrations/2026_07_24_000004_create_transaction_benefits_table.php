<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionBenefitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Non-priced perks of a transaction ("free 1 year service visit", ...).
     * They only ever arrive as part of a package, so package_id/package_name
     * record where the perk came from and the money stays 0 -- the package
     * line in transaction_packages already carries the price.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_benefits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();

            // Which package granted this benefit (null = added by hand).
            $table->unsignedBigInteger('package_id')->nullable()->index();
            $table->string('package_name')->nullable();

            // Snapshot at time of sale.
            $table->string('benefit_name')->nullable();

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
        Schema::dropIfExists('transaction_benefits');
    }
}
