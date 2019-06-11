<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('plan');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('next_plan')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('tax_percentage', 6, 4)->default(0);
            $table->datetime('ends_at')->nullable();
            $table->datetime('trial_ends_at')->nullable();
            $table->datetime('cycle_started_at');
            $table->datetime('cycle_ends_at')->nullable();
            $table->unsignedBigInteger('scheduled_order_item_id')->nullable();
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
        Schema::dropIfExists('subscriptions');
    }
}
