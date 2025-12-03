<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->boolean('uses_digital_pass')
                ->default(false)
                ->after('checked_in_by');

            $table->enum('digital_pass_method', ['voice', 'face', 'any'])
                ->nullable()
                ->after('uses_digital_pass');

            // These store embeddings at registration time (optional but recommended)
            $table->json('voice_embedding_snapshot')
                ->nullable()
                ->after('digital_pass_method');

            $table->json('face_embedding_snapshot')
                ->nullable()
                ->after('voice_embedding_snapshot');
        });
    }

    public function down()
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('uses_digital_pass');
            $table->dropColumn('digital_pass_method');
            $table->dropColumn('voice_embedding_snapshot');
            $table->dropColumn('face_embedding_snapshot');
        });
    }
};
