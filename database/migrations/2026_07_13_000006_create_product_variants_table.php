<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductVariantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Each former web_properties row is now a column here. One row = one website,
     * enabling multi-website control via website_id on related tables.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('variant_name');
            $table->decimal('variant_price', 12, 2)->nullable();
            $table->text('variant_description')->nullable();
            $table->integer('variant_color')->nullable();
            $table->integer('variant_weight')->nullable();
            $table->integer('variant_width')->nullable();
            $table->integer('variant_length')->nullable();
            $table->integer('variant_height')->nullable();
            $table->text('remark')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_variants');
    }
}
