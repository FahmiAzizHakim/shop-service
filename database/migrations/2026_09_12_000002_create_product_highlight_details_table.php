<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductHighlightDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * One row = one product in a highlight. Nothing but the pairing: what is
     * shown about the product is read off the product itself.
     *
     * Thinner than package_details, which it otherwise mirrors, because a
     * highlight makes none of the choices a package does. There is no qty --
     * a product is in the set or it is not, and "two of this one" means
     * nothing on a shelf. There is no line_type either: a package line can be
     * a product, a charge or a plain perk, while a highlight is only ever a
     * list of products.
     *
     * The pair is unique: the same product twice in one highlight would render
     * the same card twice, which is never what an editor meant. ON DELETE
     * CASCADE on highlight_id so deleting a highlight takes its lines with it.
     *
     * product_id is indexed without a foreign key, following package_details:
     * a deleted product has its lines cleared by ProductService::delete()
     * instead, which is also what keeps the delete path readable in one place.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_highlight_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_highlight_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['product_highlight_id', 'product_id'], 'product_highlight_details_UN');

            $table->foreign('product_highlight_id')
                ->references('id')->on('product_highlights')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_highlight_details');
    }
}
