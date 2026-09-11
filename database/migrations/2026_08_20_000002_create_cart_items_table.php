<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Lines of a guest cart. item_key is the identifier the checkout page
     * works with -- 'p3' (product), 'p3v1' (product + variant), 'pkg2'
     * (package), 's4' (service) -- and is what the cart is rebuilt from.
     *
     * item_type plus the three foreign keys are the same thing already parsed,
     * so carts can be reported on without picking item_key apart in SQL.
     *
     * Deliberately no price snapshot: a saved cart is re-priced from the
     * catalogue on every render, so a cart left for a week cannot check out
     * at last week's price.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id')->index();

            $table->string('item_key', 60);
            $table->string('item_type', 20)->nullable();   // product|variant|package|service

            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('product_variant_id')->nullable()->index();
            $table->unsignedBigInteger('package_id')->nullable()->index();

            $table->integer('qty')->default(1);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // A line is unique within its cart; adding again bumps the qty.
            $table->unique(['cart_id', 'item_key'], 'cart_items_cart_key_UN');

            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cart_items');
    }
}
