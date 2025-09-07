<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_ticket_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->decimal('price', 10, 2)->default(0);      // major units, like your event.ticket_cost
            $t->unsignedInteger('capacity')->nullable();  // optional per-type cap
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('event_ticket_categories');
    }
};
