<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('artisan_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('shop_name', 255);
            $table->unsignedBigInteger('shop_address_id')->nullable();
            $table->text('shop_description')->nullable();
            $table->string('shop_logo_path', 1024)->nullable();
            $table->string('slang', 50)->nullable();
            $table->string('business_license_number', 100)->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->string('bank_account_name', 255)->nullable();
            $table->string('bank_name', 255)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_iban', 64)->nullable();
            $table->unsignedBigInteger('business_license_document_id')->nullable();
            $table->unsignedBigInteger('tax_registration_document_id')->nullable();
            $table->unsignedBigInteger('identity_document_id')->nullable();
            $table->json('additional_documents')->nullable();
            $table->enum('approval_status', ['pending','approved','rejected','revoked'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('shop_address_id')->references('id')->on('user_addresses')->onDelete('set null');
            $table->foreign('business_license_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('tax_registration_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('identity_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('artisan_profiles');
    }
};
