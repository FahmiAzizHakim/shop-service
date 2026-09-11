<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Shipping address for a transaction (1:1). glb_* region codes are stored
     * alongside their names (snapshot), same approach used at checkout confirm.
     * delivery_fee mirrors the fare resolved from the chosen area.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->unique();

            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 30)->nullable();

            // Region codes (glb_*), no DB FK since these are string PKs on glb tables.
            $table->string('province_code', 60)->nullable();
            $table->string('city_code', 60)->nullable();
            $table->string('district_code', 60)->nullable();
            $table->string('subdistrict_code', 60)->nullable();

            // Region name snapshots.
            $table->string('province_name')->nullable();
            $table->string('city_name')->nullable();
            $table->string('district_name')->nullable();
            $table->string('subdistrict_name')->nullable();

            $table->text('address_detail')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->text('notes')->nullable();

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
        Schema::dropIfExists('transaction_addresses');
    }
}
