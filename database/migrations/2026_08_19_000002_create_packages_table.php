<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePackagesTable extends Migration
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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->index();
            $table->string('package_name');
            $table->string('package_code');
            $table->decimal('package_price', 12, 2)->default(0.00);
            $table->decimal('package_discount', 12, 2)->default(0.00);
            $table->text('package_description')->nullable();
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
        Schema::dropIfExists('packages');
    }
}
