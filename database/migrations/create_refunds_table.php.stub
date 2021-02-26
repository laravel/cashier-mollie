<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRefundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('original_order_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('mollie_refund_id');
            $table->string('mollie_refund_status');
            $table->integer('total');
            $table->string('currency', 3);
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
        Schema::dropIfExists('refunds');
    }
}
