<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddServiceIdToPackagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Which service a package belongs to, so packages can be listed per
     * service instead of all together.
     *
     * Nullable: a package that spans services (or has not been assigned one
     * yet) keeps working, and the public site treats null as "not tied to a
     * single service". Indexed, no foreign key -- the same shape as
     * products.service_id, which this mirrors.
     *
     * Existing rows are backfilled from the service of the first product in
     * the package, which is where the package's service was implied before
     * this column existed.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('website_id')->index();
        });

        // Backfill: the service of the package's first product line, but only
        // when that service belongs to the same website as the package.
        DB::statement("
            UPDATE packages p
            JOIN (
                SELECT pd.package_id, MIN(pr.service_id) AS service_id
                FROM package_details pd
                JOIN products pr ON pr.id = pd.product_id
                WHERE pd.product_id IS NOT NULL
                GROUP BY pd.package_id
            ) src ON src.package_id = p.id
            JOIN services s ON s.id = src.service_id AND s.website_id = p.website_id
            SET p.service_id = src.service_id
            WHERE p.service_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropIndex(['service_id']);
            $table->dropColumn('service_id');
        });
    }
}
