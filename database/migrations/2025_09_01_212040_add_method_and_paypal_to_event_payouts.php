<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->string('method', 20)->default('bank')->after('currency');
            $table->string('paypal_email')->nullable()->after('method');
            $table->string('bank_country', 2)->nullable()->after('paypal_email');
        });
    }
    public function down(): void {
        Schema::table('event_payouts', function (Blueprint $table) {
            $table->dropColumn(['method', 'paypal_email', 'bank_country']);
        });
    }
};

