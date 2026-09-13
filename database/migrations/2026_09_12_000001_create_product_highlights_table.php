<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductHighlightsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * A named, hand-picked set of products: "Best Seller", "New Arrivals",
     * "Ramadan Picks". The name is the heading the storefront prints above the
     * row; the products it contains are the detail lines in
     * product_highlight_details.
     *
     * Not a category, and the difference is the point. A category is a
     * property of a product -- one taxonomy, maintained per product, which the
     * catalogue is browsed by. A highlight is a property of the page: an
     * editorial choice about what to show first this month, which changes
     * without any product changing. Overlapping is normal, being in none is
     * normal, and the same product sitting in three highlights is the feature
     * rather than a data problem.
     *
     * Deliberately carries no price, no image and no copy. Everything shown
     * about a product comes off the product, so a highlight cannot drift out
     * of step with the catalogue it points at -- and re-pricing a product is
     * never a reason to edit a highlight.
     *
     * website_id is a plain column, as it is on packages: the websites live in
     * website-service and the two databases only agree on the ids.
     *
     * No order column on purpose, matching package_details: the rows read in
     * the order they were added (see ProductHighlight::scopeOrdered, which is
     * the one place that changes if this ever needs arranging).
     *
     * is_active is what a highlight is for. A seasonal set is built once and
     * run again next year, so ending one is a switch rather than a delete.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_highlights', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->index();

            $table->string('highlight_name');

            $table->boolean('is_active')->default(true);

            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // One name per site: two "Best Seller" rows are a mistake, and the
            // name is what the storefront prints as the heading.
            $table->unique(['website_id', 'highlight_name'], 'product_highlights_website_name_UN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_highlights');
    }
}
