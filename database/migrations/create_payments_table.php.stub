<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('mollie_payment_id');
            $table->string('mollie_payment_status');
            $table->string('mollie_mandate_id')->nullable();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('currency', 3);
            $table->unsignedInteger('amount')->default(0);
            $table->unsignedInteger('amount_refunded')->default(0);
            $table->unsignedInteger('amount_charged_back')->default(0);
            $table->text('first_payment_actions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
