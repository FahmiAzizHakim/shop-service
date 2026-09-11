<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveryPricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delivery_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->index();
            // Which glb level this price applies to. Minimum level is "city":
            // provinces / countries are intentionally not allowed.
            // The matching glb code:
            //   city        -> glb_cities.city_code
            //   district    -> glb_districts.district_code
            //   subdistrict -> glb_subdistricts.subdistrict_code
            // No DB-level FK because the target table depends on area_level;
            // it is validated in the application layer instead.
            $table->string('city_code', 60)->index();
            $table->string('district_code', 60)->nullable()->index();
            $table->string('subdistrict_code', 60)->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrent();

            // Each area can be priced at most once per website + level.
            // This is what guarantees "one city price = one row".
            $table->unique(['website_id', 'city_code', 'district_code', 'subdistrict_code'], 'delivery_prices_area_UN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delivery_prices');
    }
}
