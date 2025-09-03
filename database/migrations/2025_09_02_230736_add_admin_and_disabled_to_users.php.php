<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }
            if (!Schema::hasColumn('users', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('is_admin');
            }
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_disabled')) $table->dropColumn('is_disabled');
            if (Schema::hasColumn('users', 'is_admin')) $table->dropColumn('is_admin');
        });
    }
};