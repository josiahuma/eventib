<?php

// database/migrations/2025_10_02_000000_add_qr_token_to_event_registrations.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('status');
            $table->index(['event_id', 'qr_token']);
        });
    }

    public function down(): void {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'qr_token']);
            $table->dropColumn('qr_token');
        });
    }
};

