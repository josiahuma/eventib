<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_payout_methods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['bank','paypal']);       // one paypal total, many bank (1 per country)
            $t->char('country', 2)->nullable();        // required for bank, null or 'ZZ' for paypal
            $t->string('paypal_email')->nullable();    // for paypal

            // generic bank fields (labels vary per country)
            $t->string('account_name')->nullable();
            $t->string('account_number', 64)->nullable();
            $t->string('sort_code', 64)->nullable();   // sort/routing/ifsc/bank code
            $t->string('iban', 64)->nullable();
            $t->string('swift', 64)->nullable();

            $t->timestamps();

            // enforce 1 bank per (user,country) and 1 paypal per user
            $t->unique(['user_id','type','country']);          // (paypal stored with country 'ZZ')
        });
    }
    public function down(): void { Schema::dropIfExists('user_payout_methods'); }
};
