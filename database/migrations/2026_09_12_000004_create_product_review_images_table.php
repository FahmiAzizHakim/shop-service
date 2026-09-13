<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductReviewImagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * The photos a buyer attached to a review. Many per review, none required
     * -- a review is words first, and the pictures are what make it worth
     * reading.
     *
     * Shaped like product_images, which is the other table in this service
     * holding uploaded pictures, minus the two columns that only make sense
     * for a catalogue: there is no `order`, because a buyer's photos are a set
     * rather than an arrangement and they read in the order they were
     * uploaded, and no is_active, because a photo is taken down with the
     * review it belongs to or not at all. A single objectionable photo is a
     * reason to unpublish the review, not to silently edit what someone wrote.
     *
     * image_url holds the path UploadService returns (uploads/review/...),
     * relative, and is made absolute through asset() at the resource -- the
     * same contract every other uploaded image here keeps, so the files are
     * served by the gateway's document root like the rest.
     *
     * ON DELETE CASCADE so removing a review takes its photos with it. The
     * files on disk are removed by ProductReviewService, which is the only
     * thing that can: a foreign key knows nothing about the filesystem.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_review_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_review_id')->index();

            $table->string('image_name');
            $table->string('image_url');

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_review_id')
                ->references('id')->on('product_reviews')
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
        Schema::dropIfExists('product_review_images');
    }
}
