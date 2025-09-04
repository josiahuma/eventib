<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('event_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('registration_id')->constrained('event_registrations')->cascadeOnDelete();
            $table->unsignedInteger('index')->default(0); // 0-based within a registration
            $table->string('serial', 64)->unique();
            $table->string('token', 64)->unique();       // HMAC token
            $table->enum('status', ['valid','revoked'])->default('valid');
            $table->timestamp('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['event_id','registration_id']);
            $table->index(['event_id','token']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('event_tickets');
    }
};