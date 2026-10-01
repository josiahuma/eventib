<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->json('faqs')->nullable());
        Schema::create('organizer_alert_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamp('enabled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'organizer_id']);
        });
        Schema::create('organizer_event_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('organizer_alert_subscriptions')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['subscription_id', 'event_id']);
            $table->index(['status', 'id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('organizer_event_alerts');
        Schema::dropIfExists('organizer_alert_subscriptions');
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('faqs'));
    }
};
