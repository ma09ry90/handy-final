<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('user_addresses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('label', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('subcity', 100)->nullable();
            $table->string('zone', 100)->nullable();
            $table->string('wereda', 100)->nullable();
            $table->string('kebele', 100)->nullable();
            $table->string('street1', 255)->nullable();
            $table->string('street2', 255)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id','label']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_addresses');
    }
};
