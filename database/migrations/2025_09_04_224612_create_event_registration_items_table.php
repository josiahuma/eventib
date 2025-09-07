<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_registration_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $t->foreignId('event_ticket_category_id')->nullable()->constrained('event_ticket_categories')->nullOnDelete();
            // snapshot so price/name changes later don’t affect the purchase
            $t->string('snapshot_name');
            $t->decimal('unit_price', 10, 2)->default(0);
            $t->unsignedInteger('quantity')->default(1);
            $t->decimal('line_total', 10, 2)->default(0);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('event_registration_items');
    }
};
