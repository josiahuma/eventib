<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_tickets', function (Blueprint $t) {
            if (!Schema::hasColumn('event_tickets', 'event_ticket_category_id')) {
                $t->foreignId('event_ticket_category_id')
                  ->nullable()
                  ->after('event_id')
                  ->constrained('event_ticket_categories')
                  ->nullOnDelete();
            }
        });
    }
    public function down(): void {
        Schema::table('event_tickets', function (Blueprint $t) {
            if (Schema::hasColumn('event_tickets', 'event_ticket_category_id')) {
                $t->dropConstrainedForeignId('event_ticket_category_id');
            }
        });
    }
};
