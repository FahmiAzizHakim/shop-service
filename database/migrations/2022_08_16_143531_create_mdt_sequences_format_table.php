<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMdtSequencesFormatTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mdt_sequences_format', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_name');
            $table->string('sequence_prefix');
            $table->string('description')->nullable();
            $table->integer('sequence_length');
            $table->string('sequence_format1')->nullable();
            $table->string('sequence_format2')->nullable();
            $table->string('sequence_format3')->nullable();
            $table->string('sequence_format4')->nullable();
            $table->string('sequence_format5')->nullable();
            $table->boolean('monthly_reset')->default(1);
            $table->boolean('is_active')->default(1);
            $table->string('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by')->nullable();
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
        Schema::dropIfExists('mdt_sequences_format');
    }
}
