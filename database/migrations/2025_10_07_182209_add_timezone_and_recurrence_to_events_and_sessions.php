<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Add timezone column to event_sessions table
         */
        if (Schema::hasTable('event_sessions') && !Schema::hasColumn('event_sessions', 'timezone')) {
            Schema::table('event_sessions', function (Blueprint $table) {
                // Add after session_date (since you don’t have session_time)
                $table->string('timezone', 100)->nullable()->default('UTC')->after('session_date');
            });
        }

        /**
         * Add recurrence fields to events table
         */
        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                if (!Schema::hasColumn('events', 'is_recurring')) {
                    $table->boolean('is_recurring')->default(false)->after('banner_url');
                }
                if (!Schema::hasColumn('events', 'recurrence_type')) {
                    $table->string('recurrence_type')->nullable()->after('is_recurring');
                }
                if (!Schema::hasColumn('events', 'recurrence_interval')) {
                    $table->integer('recurrence_interval')->default(1)->after('recurrence_type');
                }
                if (!Schema::hasColumn('events', 'recurrence_count')) {
                    $table->integer('recurrence_count')->nullable()->after('recurrence_interval');
                }
                if (!Schema::hasColumn('events', 'recurrence_end_date')) {
                    $table->date('recurrence_end_date')->nullable()->after('recurrence_count');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_sessions')) {
            Schema::table('event_sessions', function (Blueprint $table) {
                if (Schema::hasColumn('event_sessions', 'timezone')) {
                    $table->dropColumn('timezone');
                }
            });
        }

        if (Schema::hasTable('events')) {
            Schema::table('events', function (Blueprint $table) {
                $drops = [
                    'is_recurring',
                    'recurrence_type',
                    'recurrence_interval',
                    'recurrence_count',
                    'recurrence_end_date',
                ];
                foreach ($drops as $col) {
                    if (Schema::hasColumn('events', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
