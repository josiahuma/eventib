<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/XXXX_create_user_digital_passes_table.php
        Schema::create('user_digital_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Voice
            $table->json('voice_embedding')->nullable();   // averaged vector
            $table->timestamp('voice_enrolled_at')->nullable();

            // Face
            $table->json('face_embedding')->nullable();    // averaged vector
            $table->timestamp('face_enrolled_at')->nullable();

            // Simple flags
            $table->boolean('is_active')->default(false);  // true when at least one modality enrolled
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_digital_passes');
    }
};
