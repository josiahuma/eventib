<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('digital_pass_mode', ['off', 'optional', 'required'])
                ->default('off')
                ->after('organizer_id'); // adjust placement as you like

            $table->enum('digital_pass_methods', ['voice', 'face', 'both'])
                ->default('both')
                ->after('digital_pass_mode');
        });
    }

    public function down()
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('digital_pass_mode');
            $table->dropColumn('digital_pass_methods');
        });
    }
};

