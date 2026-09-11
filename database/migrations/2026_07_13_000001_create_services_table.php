<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServicesTable extends Migration
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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->nullable()->index();
            $table->string('service_name')->nullable();
            $table->string('service_title')->nullable();
            $table->string('service_subtitle')->nullable();
            $table->text('service_description')->nullable();
            $table->string('service_image')->nullable(); // color | size | gradient | text
            $table->string('service_icon')->nullable(); // Colors | Layout | Gradients
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
        Schema::dropIfExists('services');
    }
}
