<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * What a buyer said about one product they bought.
     *
     * Every review is a verified purchase, and the table is shaped so it
     * cannot be anything else: a row names both the product and the
     * transaction it was bought on, and the pair is unique. There is no way to
     * store a review that is not attached to an order, so "is this reviewer a
     * real customer" is never a question anyone has to ask of the data.
     *
     * transaction_id is how that is proved, but it is not how a review is
     * submitted. A transaction id is a small integer and guessable; the
     * receipt token is not, which is why the public endpoint is
     * POST /api/v1/sites/{website}/receipt/{token}/reviews and the
     * transaction id is resolved from the token rather than accepted from the
     * body. Holding the receipt is the credential -- the same thing that
     * already lets a buyer read the order and upload proof of payment.
     *
     * Unique on (transaction_id, product_id): one review per product per
     * order. A buyer who ordered the same product twice on one receipt is
     * reviewing the product, not the line, and a receipt that could be
     * re-submitted is a receipt that could flood a product with praise.
     * Editing is then the natural next thing to want and deliberately not
     * here: see ProductReviewService, which rejects a second submission rather
     * than quietly overwriting the first.
     *
     * website_id is denormalised off the transaction, as it is on
     * transactions themselves: the public read is "the reviews of this product
     * on this site", and carrying the column means that question is one index
     * away instead of a join through orders.
     *
     * reviewer_name is snapshotted from the order rather than joined, for the
     * reason transaction_details snapshots product names: the receipt records
     * what was true at the time, and a customer later changing their details
     * must not silently re-attribute a review they already left.
     *
     * is_published starts true. The purchase is the spam filter -- somebody
     * had to buy the thing -- so holding every review in a queue would mostly
     * be a queue nobody empties, and a buyer who writes a review and cannot
     * find it assumes the site is broken. What the flag is for is the other
     * direction: taking down the occasional abusive one without deleting the
     * evidence. Flip the default here if approval-first is ever wanted; the
     * admin screen already writes both values.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('transaction_id')->index();

            // Snapshot of who left it, taken from the order.
            $table->string('reviewer_name', 191)->nullable();

            // 1..5. Checked in the FormRequest -- MariaDB 10.1 has no CHECK
            // constraints, so the column cannot hold the rule itself.
            $table->unsignedTinyInteger('rating');

            $table->text('review_text')->nullable();

            $table->boolean('is_published')->default(true);

            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // One review per product per order. This is what "verified
            // purchase" is enforced by, not merely documented as.
            $table->unique(['transaction_id', 'product_id'], 'product_reviews_trx_product_UN');

            // The public read: this product's published reviews, newest first.
            $table->index(['product_id', 'is_published'], 'product_reviews_product_published_IX');

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
        Schema::dropIfExists('product_reviews');
    }
}
