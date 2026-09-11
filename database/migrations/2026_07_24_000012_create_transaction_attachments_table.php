<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionAttachmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Images/files attached to a transaction: payment proof, package photo,
     * proof of delivery, etc. `type` is a short slug (see TransactionAttachment
     * model constants). file_path is a public path resolvable via asset().
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->index();

            $table->string('type', 30)->default('PAYMENT')->index(); // PAYMENT | PACKAGE | DELIVERY | OTHER
            $table->string('file_path');           // e.g. uploads/transaction/xxx.jpg
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('note')->nullable();

            $table->string('uploaded_by', 100)->nullable(); // email or "customer"
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_attachments');
    }
}
