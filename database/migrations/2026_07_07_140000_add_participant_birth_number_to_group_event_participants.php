<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_event_participants', function (Blueprint $table) {
            $table->string('participant_birth_number')->nullable()->after('participant_phone');
        });
    }

    public function down(): void
    {
        Schema::table('group_event_participants', function (Blueprint $table) {
            $table->dropColumn('participant_birth_number');
        });
    }
};
