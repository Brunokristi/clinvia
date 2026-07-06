<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_event_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('notification_key')->unique();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('event_type')->nullable();
            $table->string('action');
            $table->string('recipient')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_event_notification_logs');
    }
};
