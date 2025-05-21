<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShippingCostsTable extends Migration
{
    public function up()
    {
        Schema::create('shipping_costs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('courier', 50);
            $table->string('postcode', 10);
            $table->string('suburb', 100);
            $table->integer('weight_kg');
            $table->decimal('cost_aud', 10, 2);
            $table->timestamps();
            $table->index(['postcode', 'courier', 'weight_kg'], 'idx_postcode_courier_weight');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shipping_costs');
    }
}