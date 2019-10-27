<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExchangesSymbolsCandlesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('exchanges', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->index();
            $table->string('long_name');
        });
        DB::statement('ALTER TABLE exchanges ADD global_options MEDIUMBLOB NULL DEFAULT NULL');

        Schema::create('symbols', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('exchange_id')->unsigned()->index();
            $table->string('name')->index();
            $table->string('long_name');
        });

        Schema::create('candles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('time')->unsigned()->index();
            $table->integer('exchange_id')->unsigned()->index();
            $table->integer('symbol_id')->unsigned()->index();
            $table->integer('resolution')->unsigned();
            $table->decimal('open', 11, 4)->unsigned();
            $table->decimal('high', 11, 4)->unsigned();
            $table->decimal('low', 11, 4)->unsigned();
            $table->decimal('close', 11, 4)->unsigned();
            $table->bigInteger('volume')->unsigned()->nullable()->default(NULL);
            $table->index(['symbol_id', 'resolution']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exchanges');
        Schema::dropIfExists('symbols');
        Schema::dropIfExists('candles');
    }
}
