<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('number');
            $table->string('currency', 3);
            $table->integer('subtotal');
            $table->integer('tax');
            $table->integer('total');
            $table->integer('balance_before')->default(0);
            $table->integer('credit_used')->default(0);
            $table->integer('total_due');
            $table->string('mollie_payment_id')->nullable();
            $table->string('mollie_payment_status', 16)->nullable();
            $table->datetime('processed_at')->nullable();
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
        Schema::dropIfExists('orders');
    }
}
