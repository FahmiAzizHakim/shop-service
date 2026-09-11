<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCartsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Guest carts. There is no login on the public site, so a cart belongs to
     * a browser: cart_token is minted on first visit and kept in a long-lived
     * cookie, and every later request finds the cart by (website_id, token).
     *
     * The user agent / IP are diagnostics only -- they are far too unstable
     * and too widely shared to identify a browser with.
     *
     * user_id stays null for guests; it is here so a future login can claim
     * an existing cart instead of losing it.
     *
     * No created_by/updated_by: nobody is signed in to attribute a cart to.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->nullable()->index();

            $table->string('cart_token', 64);

            $table->unsignedBigInteger('user_id')->nullable()->index();

            // Diagnostics: who last touched this cart.
            $table->string('session_id', 191)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Lets stale carts be pruned without reading the item rows.
            $table->timestamp('last_activity_at')->nullable()->index();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // One cart per browser per website.
            $table->unique(['website_id', 'cart_token'], 'carts_website_token_UN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('carts');
    }
}
