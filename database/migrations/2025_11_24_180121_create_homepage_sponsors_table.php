<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // e.g. Hyde Park Winter Wonderland
            $table->string('website_url')->nullable();     // link to sponsor site
            $table->string('logo_path')->nullable();       // storage path for logo
            $table->string('background_path')->nullable(); // storage path for skin/background
            $table->unsignedInteger('priority')->default(10); // lower = more important
            $table->boolean('is_active')->default(true);

            // Optional date window for campaigns
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sponsors');
    }
};
