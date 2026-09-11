<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdtSequencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mdt_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('entitycode', 60)->nullable();
            $table->string('sequence_prefix', 60);
            $table->string('sequence_name');
            $table->integer('year')->default(0);
            $table->integer('month')->default(0);
            $table->integer('sequence_length');
            $table->integer('sequence_number');
            $table->integer('increment')->nullable();
            $table->bigInteger('min_value')->nullable();
            $table->bigInteger('max_value')->nullable();
            $table->bigInteger('cur_value')->nullable();
            $table->boolean('cycle')->default(0);
            $table->boolean('is_active')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mdt_sequences');
    }
}
