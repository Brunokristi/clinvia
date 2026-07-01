<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_disabled_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('date');
            $table->string('title');
            $table->string('type')->nullable();
            $table->text('reason')->nullable();

            $table->timestamps();

            $table->unique(['branch_id', 'date']);
            $table->index(['branch_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_disabled_days');
    }
};