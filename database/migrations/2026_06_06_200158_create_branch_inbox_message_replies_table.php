<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('branch_inbox_message_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_inbox_message_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('subject');
            $table->longText('body');
            $table->string('recipient_email');
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_inbox_message_replies');
    }
};
