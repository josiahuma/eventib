<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('is_promoted');
            }
        });
    }
    public function down(): void {
        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'is_disabled')) $table->dropColumn('is_disabled');
        });
    }
};