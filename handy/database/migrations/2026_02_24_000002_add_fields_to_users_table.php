<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name', 100)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name', 100)->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'phone_number')) {
                $table->string('phone_number', 25)->nullable()->unique()->after('email');
            }

            if (! Schema::hasColumn('users', 'role_id')) {
                $table->unsignedTinyInteger('role_id')->default(1)->after('password');
            }

            if (! Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['active','blocked','pending'])->default('active')->after('role_id');
            }

            if (! Schema::hasColumn('users', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('account_status');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('birthdate');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','role_id')) {
                $table->foreign('role_id')->references('id')->on('roles')->onUpdate('cascade')->onDelete('restrict');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users','last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users','birthdate')) {
                $table->dropColumn('birthdate');
            }
            if (Schema::hasColumn('users','account_status')) {
                $table->dropColumn('account_status');
            }
            if (Schema::hasColumn('users','role_id')) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            }
            if (Schema::hasColumn('users','phone_number')) {
                try {
                    $table->dropUnique(['phone_number']);
                } catch (\Exception $e) {
                    // ignore if index name differs
                }
                $table->dropColumn('phone_number');
            }
        });
    }
};
