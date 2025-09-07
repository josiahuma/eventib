<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('homepage_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('image_path');               // storage path (disk: public)
            $table->string('link_url')->nullable();     // optional click-through
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('homepage_slides');
    }
};
