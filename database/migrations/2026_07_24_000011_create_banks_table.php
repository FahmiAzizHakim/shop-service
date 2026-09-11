<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Bank accounts a customer can transfer payment to. Scoped per website
     * like the other master tables. Shown on the public receipt page.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->nullable()->index();
            $table->string('bank_name');
            $table->string('bank_account', 60);       // account number
            $table->string('account_name');           // account holder name
            $table->string('branch')->nullable();
            $table->string('logo')->nullable();
            $table->text('remark')->nullable();
            $table->boolean('is_active')->default(true)->index();
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
        Schema::dropIfExists('banks');
    }
}
