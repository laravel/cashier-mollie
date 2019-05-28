<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('process_at');
            $table->string('orderable_type')->nullable();
            $table->unsignedInteger('orderable_id')->nullable();
            $table->string('owner_type');
            $table->unsignedInteger('owner_id');
            $table->string('description');
            $table->json('description_extra_lines')->nullable();
            $table->string('currency', 3);
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_price');
            $table->decimal('tax_percentage', 6, 4);
            $table->unsignedInteger('order_id')->nullable();
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
        Schema::dropIfExists('order_items');
    }
}
