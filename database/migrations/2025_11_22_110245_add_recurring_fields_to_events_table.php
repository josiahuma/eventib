<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // We ALREADY have is_recurring from an earlier migration,
        // so we only add a human-friendly summary field here.
        if (Schema::hasTable('events') && ! Schema::hasColumn('events', 'recurrence_summary')) {
            Schema::table('events', function (Blueprint $table) {
                $table->string('recurrence_summary', 255)
                    ->nullable()
                    ->after('is_recurring');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('events') && Schema::hasColumn('events', 'recurrence_summary')) {
            Schema::table('events', function (Blueprint $table) {
                $table->dropColumn('recurrence_summary');
            });
        }
    }
};

