<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    // Users Table
    Schema::table('users', function (Blueprint $table) {
        $table->index('role_id');      // Speeds up role checks
        $table->index('account_status'); // Speeds up active checks
        $table->index('email');         // Speeds up login
    });

    // Artisan Profiles Table
    Schema::table('artisan_profiles', function (Blueprint $table) {
        $table->index('user_id');
        $table->index('approval_status'); // Speeds up Admin Dashboard queries
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_and_profiles', function (Blueprint $table) {
            //
        });
    }
};
