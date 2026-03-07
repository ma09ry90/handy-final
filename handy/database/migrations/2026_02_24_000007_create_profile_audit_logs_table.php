<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('profile_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 100);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['entity_type','entity_id']);
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('profile_audit_logs');
    }
};
