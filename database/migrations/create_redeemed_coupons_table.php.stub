<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRedeemedCouponsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('redeemed_coupons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedInteger('times_left')->default(1);
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
        Schema::dropIfExists('redeemed_coupons');
    }
}
