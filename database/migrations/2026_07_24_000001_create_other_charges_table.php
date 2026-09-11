<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOtherChargesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Master data of miscellaneous charges (e.g. handling fee, insurance,
     * packaging). Scoped per website via website_id like the other master
     * tables. "code" is a short business identifier, unique per website.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('other_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->nullable()->index();
            $table->string('code', 60)->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->text('remark')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // A charge code is unique within a website.
            $table->unique(['website_id', 'code'], 'other_charges_website_code_UN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('other_charges');
    }
}
