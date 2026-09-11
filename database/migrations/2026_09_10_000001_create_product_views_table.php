<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductViewsTable extends Migration
{
    /**
     * One row per opening of a product page, and nothing about who opened it.
     *
     * The same shape as website-service's content_views, deliberately: the
     * question is the same one -- which items are looked at, and when -- and
     * two tables answering it differently would mean two ways to read the
     * answer. Everything a visitor's request could also tell us (address,
     * browser, where they came from) is data about a person and none of it is
     * needed here; the columns for it can be added the day a question needs
     * them.
     *
     * No website_id, for the reason products have none: a view belongs to a
     * website through the product, and the product through its service. See
     * ProductViewRepository, which is where that indirection is handled.
     *
     * The index is the pair rather than product_id alone: every read is "this
     * product, over this period", and a leftmost prefix still serves the
     * counts that ask for no period at all.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->timestamp('viewed_at')->useCurrent();

            $table->index(['product_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_views');
    }
}
