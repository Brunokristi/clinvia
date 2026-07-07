<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->unsignedBigInteger('root_event_id')->nullable();
            $table->string('occurrence_display_key')->nullable();
            $table->string('notification_type');
            $table->string('scope')->nullable();
            $table->string('dedupe_key')->unique();
            $table->string('payload_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['notification_type', 'sent_at']);
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['root_event_id', 'scope']);
            $table->index(['recipient_email', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
    }
};
