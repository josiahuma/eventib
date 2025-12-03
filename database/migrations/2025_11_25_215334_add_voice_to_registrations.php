<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('voice_sample_path')->nullable();
            $table->boolean('voice_enabled')->default(false);
            $table->timestamp('voice_recorded_at')->nullable();
            // For later AI:
            $table->json('voice_embedding')->nullable(); // Phase 3
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn([
                'voice_sample_path',
                'voice_enabled',
                'voice_recorded_at',
                'voice_embedding',
            ]);
        });
    }
};