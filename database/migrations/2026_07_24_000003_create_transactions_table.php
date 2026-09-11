<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * Transaction header. Monetary breakdown:
     *   price        = sum of detail subtotals (items)
     *   discount     = header-level discount
     *   delivery_fee = shipping fee (mirrors transaction_addresses.delivery_fee)
     *   charges      = sum of transaction_charges (misc other charges)
     *   total        = price - discount + delivery_fee + charges   (pre-tax)
     *   tax          = tax amount
     *   grandtotal   = total + tax
     *
     * status references codes.code where codes.parentcode = 'STS'
     * (e.g. STSSV Saved, STSPY Payment, STSPD Paid, STSDV Delivery, STSOK Completed).
     * payment_type references codes.code where parentcode = 'TRT' (Cash/Credit).
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('website_id')->nullable()->index();

            $table->string('receipt_no', 60)->nullable();
            $table->date('transaction_date')->nullable()->index();

            // Customer snapshot (order may come from a guest checkout).
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();

            // Monetary breakdown.
            $table->decimal('price', 14, 2)->default(0);        // items subtotal
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->decimal('charges', 14, 2)->default(0);      // sum of other charges
            $table->decimal('total', 14, 2)->default(0);        // pre-tax
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('grandtotal', 14, 2)->default(0);

            $table->string('payment_type', 60)->nullable();     // codes.code (TRT*)
            $table->string('status', 60)->nullable()->index();  // codes.code (STS*)
            $table->text('remark')->nullable();

            $table->string('created_by', 60)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('updated_by', 60)->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // Receipt number is unique within a website.
            $table->unique(['website_id', 'receipt_no'], 'transactions_website_receipt_UN');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
