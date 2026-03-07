<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('delivery_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id_number', 100)->nullable();
            $table->string('driving_license_number', 100)->nullable();
            $table->date('driving_license_expiry')->nullable();
            $table->string('emergency_contact_name', 255)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->string('vehicle_type', 50)->nullable();
            $table->string('vehicle_plate_number', 50)->nullable();
            $table->string('vehicle_model', 100)->nullable();
            $table->string('vehicle_color', 50)->nullable();
            $table->unsignedBigInteger('driving_license_document_id')->nullable();
            $table->unsignedBigInteger('national_id_document_id')->nullable();
            $table->unsignedBigInteger('vehicle_registration_document_id')->nullable();
            $table->json('additional_documents')->nullable();
            $table->string('assigned_zone', 100)->nullable();
            $table->enum('employment_type', ['full_time','part_time','contractor'])->default('contractor');
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->enum('approval_status', ['pending','approved','rejected','suspended'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->enum('background_check_status', ['not_requested','in_progress','clear','failed'])->default('not_requested');
            $table->timestamp('background_check_date')->nullable();
            $table->string('background_check_notes', 2000)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('driving_license_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('national_id_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('vehicle_registration_document_id')->references('id')->on('documents')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_profiles');
    }
};
