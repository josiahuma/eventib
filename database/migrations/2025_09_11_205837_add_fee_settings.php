<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            if (!Schema::hasColumn('events', 'fee_mode')) {
                $table->string('fee_mode', 8)->default('absorb')->after('ticket_currency'); // 'absorb' | 'pass'
            }
            if (!Schema::hasColumn('events', 'fee_bps')) {
                $table->unsignedSmallInteger('fee_bps')->default(590)->after('fee_mode');   // 590 = 5.90%
            }
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('event_registrations', 'platform_fee')) {
                $table->decimal('platform_fee', 10, 2)->default(0)->after('amount'); // major units
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('event_registrations', 'platform_fee')) {
                $table->dropColumn('platform_fee');
            }
        });

        Schema::table('events', function (Blueprint $table) {
            if (Schema::hasColumn('events', 'fee_bps')) {
                $table->dropColumn('fee_bps');
            }
            if (Schema::hasColumn('events', 'fee_mode')) {
                $table->dropColumn('fee_mode');
            }
        });
    }
};
